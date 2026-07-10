// Discord-бот для выдачи доступа к сайту модераторов.
// Главная команда: /доступ — выдаёт тебе логин/пароль.
// Доп. команды для админов: /доступ-кому, /доступ-сбросить, /доступ-удалить, /доступ-список.

require('dotenv').config();
const { Client, GatewayIntentBits, REST, Routes, SlashCommandBuilder, PermissionFlagsBits } = require('discord.js');
const fs = require('fs');
const path = require('path');

// === Конфиг (env) ===
const TOKEN     = process.env.DISCORD_TOKEN;
const CLIENT_ID = process.env.CLIENT_ID;
const GUILD_ID  = process.env.GUILD_ID || '';
const SITE_URL  = process.env.SITE_URL  || 'https://your-site.up.railway.app';
const USERS_JSON_PATH = process.env.USERS_JSON_PATH || path.join(__dirname, 'users.json');
const ROSTER_JSON_PATH = process.env.ROSTER_JSON_PATH || path.join(__dirname, 'roster.json');

if (!TOKEN || !CLIENT_ID) {
    console.error('❌ Не заданы DISCORD_TOKEN или CLIENT_ID');
    process.exit(1);
}

// === Роли ===
const ROLES = ['master', 'curator', 'chief', 'asst', 'admin'];
const ROLE_NAMES = {
    master:  'Мастер',
    curator: 'Куратор',
    chief:   'Главный куратор',
    asst:    'Ассистент админа',
    admin:   'Администратор'
};

// Discord-роли (ID на сервере) → site-роль. По убыванию приоритета.
const ROLE_MAP = [
    { site: 'admin',   id: process.env.ROLE_ID_ADMIN   || '1510990968952979597' },
    { site: 'asst',    id: process.env.ROLE_ID_ASST    || '1519019533388484710' },
    { site: 'chief',   id: process.env.ROLE_ID_CHIEF   || '1510990924887359561' }, // GLK
    { site: 'curator', id: process.env.ROLE_ID_CURATOR || '1510990823150321734' },
    { site: 'master',  id: process.env.ROLE_ID_MASTER  || '1510990764681723924' },
];

// Определяет site-роль для GuildMember.
// Сначала смотрим, есть ли у него матчинг по ID в ROLE_MAP (берём верхнюю по приоритету).
// Если ничего не подходит, но у него есть permission Administrator — даём 'admin'.
// Иначе возвращаем null (доступа нет).
function detectSiteRole(member) {
    if (!member) return null;
    for (const m of ROLE_MAP) {
        if (m.id && member.roles && member.roles.cache && member.roles.cache.has(m.id)) {
            return m.site;
        }
    }
    if (member.permissions && member.permissions.has(PermissionFlagsBits.Administrator)) {
        return 'admin';
    }
    return null;
}

// === Утилиты ===
function loadUsers() {
    try {
        if (!fs.existsSync(USERS_JSON_PATH)) return {};
        return JSON.parse(fs.readFileSync(USERS_JSON_PATH, 'utf8') || '{}');
    } catch (e) {
        console.error('Не смог прочитать users.json:', e.message);
        return {};
    }
}
function saveUsers(data) {
    const dir = path.dirname(USERS_JSON_PATH);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(USERS_JSON_PATH, JSON.stringify(data, null, 2), 'utf8');
}
function genPassword(len = 10) {
    const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let s = '';
    for (let i = 0; i < len; i++) s += chars[Math.floor(Math.random() * chars.length)];
    return s;
}
// Кириллица → латиница для генерации логина из ника Discord
const CYR = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя';
const LAT = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
function translit(s) {
    let out = '';
    for (const c of s.toLowerCase()) {
        const i = CYR.indexOf(c);
        if (i >= 0) out += LAT[i];
        else if (/[a-z0-9]/.test(c)) out += c;
        else if (/[\s\-_.]/.test(c)) out += '_';
    }
    return out.replace(/_+/g, '_').replace(/^_|_$/g, '');
}
function makeUniqueLogin(base, users) {
    let login = translit(base).slice(0, 32) || 'user';
    if (!users[login]) return login;
    for (let i = 2; i < 1000; i++) {
        const v = (login + '_' + i).slice(0, 32);
        if (!users[v]) return v;
    }
    return login + '_' + Date.now();
}
function findExistingLoginByDiscord(users, discordId) {
    for (const [login, u] of Object.entries(users)) {
        if (u.discord_id === discordId) return login;
    }
    return null;
}

// === Slash-команды ===
const commands = [
    new SlashCommandBuilder()
        .setName('доступ')
        .setDescription('Получить логин и пароль для сайта модераторов'),
].map(c => c.toJSON());

async function registerCommands() {
    const rest = new REST({ version: '10' }).setToken(TOKEN);
    try {
        if (GUILD_ID) {
            await rest.put(Routes.applicationGuildCommands(CLIENT_ID, GUILD_ID), { body: commands });
            console.log(`✅ Команды зарегистрированы на сервере ${GUILD_ID}`);
        } else {
            await rest.put(Routes.applicationCommands(CLIENT_ID), { body: commands });
            console.log('✅ Команды зарегистрированы глобально (могут появиться через 5–60 минут)');
        }
    } catch (e) {
        console.error('❌ Ошибка регистрации команд:', e);
    }
}

// === Клиент ===
// GuildMembers — нужен, чтобы тянуть список участников по ролям.
// ВАЖНО: включить «Server Members Intent» в Developer Portal → Bot.
const client = new Client({
    intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildMembers]
});

// === Сбор ростера (вышка) ===
const ROLE_PRIORITY = ['admin', 'asst', 'chief', 'curator', 'master'];
const MODER_ROLE_ID = process.env.ROLE_ID_MODER || '1148609467257208904';

function roleIdOf(siteRole) {
    const m = ROLE_MAP.find(x => x.site === siteRole);
    return m ? m.id : '';
}

async function buildRoster(guild) {
    try { await guild.members.fetch(); }
    catch (e) {
        console.error('❌ Не удалось получить участников (включи Server Members Intent в Dev Portal):', e.message);
        return null;
    }
    const out = { admin: [], asst: [], chief: [], curator: [], master: [] };
    const moderators = [];
    const ids = Object.fromEntries(ROLE_PRIORITY.map(r => [r, roleIdOf(r)]));

    guild.members.cache.forEach(m => {
        const memberRoles = m.roles.cache;

        // Модератор — отдельный список (для сверки таблицы)
        if (MODER_ROLE_ID && memberRoles.has(MODER_ROLE_ID)) {
            moderators.push({
                id: m.id,
                name: m.displayName || m.user.username,
                username: m.user.username,
                avatar: m.displayAvatarURL({ size: 128, extension: 'png' }),
                joined_at: m.joinedAt ? m.joinedAt.toISOString().slice(0, 10) : null
            });
        }

        let cat = null;
        for (const r of ROLE_PRIORITY) {
            if (ids[r] && memberRoles.has(ids[r])) { cat = r; break; }
        }
        if (!cat) return;
        out[cat].push({
            id: m.id,
            name: m.displayName || m.user.username,
            username: m.user.username,
            avatar: m.displayAvatarURL({ size: 128, extension: 'png' }),
            joined_at: m.joinedAt ? m.joinedAt.toISOString().slice(0, 10) : null
        });
    });

    // Сортировка по дате вступления (старые первыми)
    Object.values(out).forEach(arr => arr.sort((a, b) => (a.joined_at || '').localeCompare(b.joined_at || '')));
    moderators.sort((a, b) => (a.username || '').localeCompare(b.username || ''));
    return { staff: out, moderators };
}

async function saveRoster(guild) {
    if (!guild) return;
    const result = await buildRoster(guild);
    if (!result) return;
    const { staff, moderators } = result;
    const dir = path.dirname(ROSTER_JSON_PATH);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(ROSTER_JSON_PATH, JSON.stringify({
        updated_at: new Date().toISOString(),
        roster: staff,
        moderators
    }, null, 2));
    const counts = Object.fromEntries(Object.entries(staff).map(([k, v]) => [k, v.length]));
    counts.moderators = moderators.length;
    console.log('📋 roster.json обновлён:', counts);
}

client.once('ready', async () => {
    console.log(`🤖 Бот запущен как ${client.user.tag}`);
    console.log(`📂 users.json:  ${USERS_JSON_PATH}`);
    console.log(`📂 roster.json: ${ROSTER_JSON_PATH}`);
    console.log(`🌐 Сайт: ${SITE_URL}`);
    registerCommands();

    if (GUILD_ID) {
        try {
            const guild = await client.guilds.fetch(GUILD_ID);
            await saveRoster(guild);
            // Каждые 5 минут на случай если события пропустим
            setInterval(() => saveRoster(guild).catch(() => {}), 5 * 60 * 1000);
        } catch (e) {
            console.error('Не удалось получить гильдию:', e.message);
        }
    }
});

// Обновляем ростер при изменениях
async function refreshRoster(guild) {
    try { await saveRoster(guild); } catch {}
}
client.on('guildMemberAdd',    m => GUILD_ID && m.guild.id === GUILD_ID && refreshRoster(m.guild));
client.on('guildMemberRemove', m => GUILD_ID && m.guild.id === GUILD_ID && refreshRoster(m.guild));
client.on('guildMemberUpdate', (_o, n) => GUILD_ID && n.guild.id === GUILD_ID && refreshRoster(n.guild));

// Создаёт/обновляет аккаунт, возвращает {login, password, role}
function issueCredentials({ discordId, displayName, role, existing = null }) {
    const users = loadUsers();
    const password = genPassword();
    let login = existing || findExistingLoginByDiscord(users, discordId);
    let created = false;
    if (!login) {
        login = makeUniqueLogin(displayName, users);
        created = true;
        users[login] = {
            password,
            role,
            discord_id: discordId,
            created_at: new Date().toISOString()
        };
    } else {
        users[login].password = password;
        if (role) users[login].role = role;
        users[login].updated_at = new Date().toISOString();
    }
    saveUsers(users);
    return { login, password, role: users[login].role, created };
}

function credsMessage({ login, password, role, siteUrl }) {
    return (
        `**Доступ к сайту модераторов**\n\n` +
        `**Логин:** \`${login}\`\n` +
        `**Пароль:** \`${password}\`\n` +
        `**Роль:** ${ROLE_NAMES[role] || role}\n\n` +
        `${siteUrl}/login.php`
    );
}

client.on('interactionCreate', async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    try {
        const cmd = interaction.commandName;

        // -------- /доступ (себе) --------
        if (cmd === 'доступ') {
            // Подтягиваем member с кэшем ролей.
            let member = interaction.member;
            if (!member?.roles?.cache?.size && interaction.guild) {
                member = await interaction.guild.members.fetch(interaction.user.id).catch(() => null);
            }
            const role = detectSiteRole(member);
            if (!role) {
                return interaction.reply({
                    content: 'У тебя нет роли стаффа на сервере (Master / Curator / GLK / Administrator). Сначала получи роль, потом вызывай команду.',
                    ephemeral: true
                });
            }
            const displayName = member?.nickname || interaction.user.globalName || interaction.user.username;

            const res = issueCredentials({
                discordId: interaction.user.id,
                displayName,
                role
            });

            await interaction.reply({
                content: credsMessage({ ...res, siteUrl: SITE_URL }),
                ephemeral: true
            });
            return;
        }

    } catch (e) {
        console.error('Ошибка команды:', e);
        const msg = 'Внутренняя ошибка: ' + (e.message || 'неизвестно');
        if (interaction.deferred || interaction.replied) {
            interaction.followUp({ content: msg, ephemeral: true }).catch(() => {});
        } else {
            interaction.reply({ content: msg, ephemeral: true }).catch(() => {});
        }
    }
});

client.login(TOKEN);
