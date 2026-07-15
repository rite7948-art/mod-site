// Сверка таблицы модеров — по образцу футика2 (check_sync.js), но для роли
// Модератор вместо Саппорта. Логинится селфботом на КАЖДЫЙ запуск, читает
// Google-таблицу и сверяет с Discord.
//
// ВАЖНО про большой сервер (135k+ участников):
// guild.members.fetch() без фильтра не успевает прогрузить роли почти ни для
// кого — приходят "пустые" профили (проверено: 135009 участников, 0 с ролью).
// Поэтому для проверки "есть ли роль у человека из таблицы" тянем участников
// АДРЕСНО, маленькими пачками по ID — это быстро и надёжно. Полный скан всех
// участников (нужен только для поиска "лишних" — тех, у кого роль есть, но
// их нет в таблице) — best-effort с таймаутом, может быть неполным на таком
// размере сервера (та же оговорка, что и в футика2).
//
// Запуск: node check_moder_sync.js  → печатает JSON в stdout и завершается.

require('dotenv').config();
const { Client } = require('discord.js-selfbot-v13');

const GUILD_ID = process.env.MODER_GUILD_ID || '531970658633252864';
const ROLE_ID  = process.env.MODER_ROLE_ID  || '1148609467257208904';
const SHEET_URL = process.env.MODER_SHEET_URL ||
    'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
const FULL_SCAN_TIMEOUT_MS = +process.env.MODER_FULL_SCAN_TIMEOUT_MS || 45000;
// Аккаунт самого селфбота — технически состоит в сервере с ролью, но это не
// человек из таблицы, поэтому в "лишних" его показывать не нужно.
const SELFBOT_ACCOUNT_ID = process.env.SELFBOT_ACCOUNT_ID || '1520428688255222008';

function parseCsv(text) {
    const rows = [];
    let row = [], field = '', inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQuotes) {
            if (c === '"') { if (text[i + 1] === '"') { field += '"'; i++; } else inQuotes = false; }
            else field += c;
        } else {
            if (c === '"') inQuotes = true;
            else if (c === ',') { row.push(field); field = ''; }
            else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
            else if (c === '\r') {}
            else field += c;
        }
    }
    if (field.length || row.length) { row.push(field); rows.push(row); }
    return rows;
}

async function getSheetData() {
    const resp = await fetch(SHEET_URL);
    if (!resp.ok) throw new Error('Sheets HTTP ' + resp.status);
    const text = await resp.text();
    if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
        throw new Error('Таблица закрыта — открой доступ «по ссылке → Читатель».');
    }
    const rows = parseCsv(text);

    // Секция "Moderators"/"Список модераторов" — обязательные (mandatory).
    // Всё, что стоит ДО неё (вышка / High staff) — ignored: если человек
    // вырос из модеров, но роль в Discord ещё не сняли, это не "лишний".
    const MOD_HEADERS = ['Moderators', 'Список модераторов'];
    let inMod = false;
    const sheetById = new Map(); // id -> {nick, rows: [rowNums]}
    const ignoredIds = new Set();
    for (let idx = 0; idx < rows.length; idx++) {
        const r = rows[idx];
        if (!r) continue;
        const hasModHeader = r.some(c => MOD_HEADERS.includes((c || '').trim()));
        if (hasModHeader) { inMod = true; continue; }
        const id = (r[2] || '').trim();
        if (!/^\d{15,22}$/.test(id)) continue;
        if (!inMod) { ignoredIds.add(id); continue; }
        const nick = (r[3] || '').trim();
        if (!sheetById.has(id)) sheetById.set(id, { nick, rows: [] });
        sheetById.get(id).rows.push(idx + 1);
    }
    return { sheetById, ignoredIds };
}

const client = new Client({ checkUpdate: false });

client.on('ready', async () => {
    try {
        const guild = client.guilds.cache.get(GUILD_ID) || await client.guilds.fetch(GUILD_ID);
        if (!guild) throw new Error('Сервер не найден: ' + GUILD_ID);

        const { sheetById, ignoredIds } = await getSheetData();

        // 1) Точечно тянем ТОЛЬКО тех, кто есть в таблице (модеры + вышка) —
        //    надёжно даже на гигантском сервере, запрос маленький и адресный.
        const targetIds = Array.from(new Set([...sheetById.keys(), ...ignoredIds]));
        const fetchedById = new Map();
        const chunkSize = 100;
        for (let i = 0; i < targetIds.length; i += chunkSize) {
            const chunk = targetIds.slice(i, i + chunkSize);
            try {
                const fetched = await guild.members.fetch({ user: chunk, withPresences: false });
                fetched.forEach(m => fetchedById.set(m.id, m));
            } catch (e) { /* участник мог выйти с сервера — пропускаем */ }
            await new Promise(r => setTimeout(r, 400));
        }

        // 2) Best-effort полный скан — для поиска "лишних" (роль есть, в
        //    таблице нет). На гигантском сервере может быть неполным.
        const membersWithRole = new Map();
        try {
            const fetchPromise = guild.members.fetch({ withPresences: false });
            const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), FULL_SCAN_TIMEOUT_MS));
            const all = await Promise.race([fetchPromise, timeoutPromise]);
            all.forEach(m => { if (m.roles.cache.has(ROLE_ID)) membersWithRole.set(m.id, m); });
        } catch (e) {
            guild.members.cache.forEach(m => { if (m.roles.cache.has(ROLE_ID)) membersWithRole.set(m.id, m); });
        }
        fetchedById.forEach(m => { if (m.roles.cache.has(ROLE_ID)) membersWithRole.set(m.id, m); });

        // 3) Сверка
        const extra = [];
        membersWithRole.forEach(m => {
            if (m.id === SELFBOT_ACCOUNT_ID) return;
            if (!sheetById.has(m.id) && !ignoredIds.has(m.id)) {
                extra.push({
                    id: m.id,
                    name: m.displayName || m.user.username,
                    username: m.user.username,
                    avatar: m.user.displayAvatarURL?.({ size: 128, format: 'png' }) || null
                });
            }
        });

        const missing = [];
        for (const [id, info] of sheetById) {
            const m = fetchedById.get(id);
            const hasRole = !!(m && m.roles.cache.has(ROLE_ID));
            if (!hasRole) missing.push({ id, nick: info.nick, rows: info.rows, in_guild: !!m });
        }

        const duplicates = [];
        for (const [id, info] of sheetById) {
            if (info.rows.length > 1) duplicates.push({ id, nick: info.nick, rows: info.rows });
        }

        console.log(JSON.stringify({
            sheet_count: sheetById.size,
            discord_count: membersWithRole.size,
            extra, missing, duplicates
        }));
    } catch (e) {
        console.log(JSON.stringify({ error: e.message }));
    } finally {
        process.exit(0);
    }
});

client.login(process.env.SELFBOT_TOKEN).catch(e => {
    console.log(JSON.stringify({ error: 'login failed: ' + e.message }));
    process.exit(1);
});
