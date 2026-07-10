// Селфбот — тянет модеров с основного Futurama-сервера
// и пишет их в moderators.json для сверки таблицы.
//
// ВНИМАНИЕ: селфбот использует user-токен (твоего аккаунта, а не бота).
// Discord это официально запрещает — используй на свой риск.
// Токен и параметры кладутся в .env:
//   SELFBOT_TOKEN=твой_user_token
//   MODER_GUILD_ID=531970658633252864
//   MODER_ROLE_ID=1148609467257208904

try { require('dotenv').config(); } catch {}
const { Client } = require('discord.js-selfbot-v13');
const fs = require('fs');
const path = require('path');

const TOKEN     = process.env.SELFBOT_TOKEN;
const GUILD_ID  = process.env.MODER_GUILD_ID || '531970658633252864';
const ROLE_ID   = process.env.MODER_ROLE_ID  || '1148609467257208904';
const OUT_PATH  = process.env.MODERATORS_JSON_PATH || path.join(__dirname, 'moderators.json');
const REFRESH_MINUTES = +process.env.MODER_REFRESH_MINUTES || 10;

if (!TOKEN) {
    console.error('❌ SELFBOT_TOKEN не задан в .env');
    process.exit(1);
}

function log(...a) { console.log('[selfbot]', ...a); }

const client = new Client({
    checkUpdate: false,
    patchVoice: false,
    ws: {
        capabilities: 30717,
        properties: {
            os: 'Windows',
            browser: 'Discord Client',
            release_channel: 'stable',
            client_version: '1.0.9033',
            os_version: '10.0.19045',
            os_arch: 'x64',
            app_arch: 'x64',
            system_locale: 'ru-RU',
            has_client_mods: false,
            browser_user_agent: 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) discord/1.0.9033 Chrome/128.0.6613.186 Electron/32.2.7 Safari/537.36',
            browser_version: '32.2.7',
            os_sdk_version: '19045',
            client_build_number: 353100,
            native_build_number: 60639,
            client_event_source: null
        },
        compress: false,
        client_state: { guild_versions: {} },
        version: 9
    }
});

async function collect() {
    try {
        const guild = client.guilds.cache.get(GUILD_ID) || await client.guilds.fetch(GUILD_ID);
        if (!guild) { log('❌ Сервер не найден:', GUILD_ID); return; }

        log(`Гильдия: ${guild.name} · участников: ${guild.memberCount}`);

        // Селфбот-v13 позволяет пофайно тянуть участников только через lazy load / scan.
        // Быстрый способ — fetchMembers на весь список + фильтр по роли.
        const members = await guild.members.fetch({ withPresences: false }).catch(async () => {
            // Фолбэк: постранично через members.list
            const all = new Map();
            let after;
            for (;;) {
                const chunk = await guild.members.list({ limit: 1000, after }).catch(() => null);
                if (!chunk || chunk.size === 0) break;
                chunk.forEach(m => all.set(m.id, m));
                if (chunk.size < 1000) break;
                after = chunk.last().id;
            }
            return all;
        });

        const moderators = [];
        members.forEach(m => {
            const roles = m.roles?.cache;
            if (!roles || !roles.has(ROLE_ID)) return;
            moderators.push({
                id: m.id,
                username: m.user?.username || m.displayName || '',
                name: m.displayName || m.user?.username || '',
                avatar: m.user?.displayAvatarURL?.({ size: 128, format: 'png' }) || null,
                joined_at: m.joinedAt ? m.joinedAt.toISOString().slice(0, 10) : null
            });
        });

        moderators.sort((a, b) => (a.username || '').localeCompare(b.username || ''));

        const dir = path.dirname(OUT_PATH);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(OUT_PATH, JSON.stringify({
            updated_at: new Date().toISOString(),
            guild_id: GUILD_ID,
            role_id: ROLE_ID,
            moderators
        }, null, 2));

        log(`✅ Модеров с ролью ${ROLE_ID}: ${moderators.length} → ${OUT_PATH}`);
    } catch (e) {
        log('❌ Ошибка сбора:', e.message);
    }
}

client.on('ready', async () => {
    log(`🤖 Залогинен как ${client.user.tag}`);
    await collect();
    setInterval(collect, REFRESH_MINUTES * 60 * 1000);
});

client.on('guildMemberUpdate', (oldM, newM) => {
    if (newM.guild.id !== GUILD_ID) return;
    const had = oldM.roles?.cache?.has(ROLE_ID);
    const has = newM.roles?.cache?.has(ROLE_ID);
    if (had !== has) collect();
});
client.on('guildMemberAdd', m => { if (m.guild.id === GUILD_ID) collect(); });
client.on('guildMemberRemove', m => { if (m.guild.id === GUILD_ID) collect(); });

client.login(TOKEN).catch(e => {
    console.error('❌ Логин селфбота не удался:', e.message);
    process.exit(1);
});
