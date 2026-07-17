// Постоянное соединение селфбота для трекера активности в голосовых
// "Комнатах" — по явному запросу владельца сайта висит в шлюзе Discord
// всегда, а не логинится короткими сеансами (так было раньше в
// voice_activity_sync.js, который остаётся как есть для локальной разработки
// через local-server.js).
//
// При подключении сперва разбирает всё, что накопилось в логе с прошлого
// раза (на случай простоя/переподключения), затем слушает новые сообщения
// канала-лога в реальном времени (`messageCreate`) — тот же формат и та же
// логика разбора "зашёл/вышел/перешёл", что и в одноразовом скрипте.

const fs = require('fs');
const { Client } = require('discord.js-selfbot-v13');

const GUILD_ID = process.env.MODER_GUILD_ID || '531970658633252864';
const ROLE_ID = process.env.MODER_ROLE_ID || '1148609467257208904';
const ROOMS_CATEGORY_ID = process.env.VOICE_ROOMS_CATEGORY_ID || '965250253873889310';
const LOG_CHANNEL_ID = process.env.VOICE_LOG_CHANNEL_ID || '965269054321471530';
const SELFBOT_ACCOUNT_ID = process.env.SELFBOT_ACCOUNT_ID || '1520428688255222008';
const FULL_SCAN_TIMEOUT_MS = Number(process.env.VOICE_FULL_SCAN_TIMEOUT_MS || 45000);
const MODER_SHEET_URL = process.env.MODER_SHEET_URL ||
    'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
const ROOMS_REFRESH_MS = Number(process.env.VOICE_ROOMS_REFRESH_MS || 30 * 60 * 1000);
const ROSTER_REFRESH_MS = Number(process.env.VOICE_ROSTER_REFRESH_MS || 10 * 60 * 1000);
const MAX_SESSIONS_PER_USER = 300;

function isoWeekKey(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = (d.getUTCDay() + 6) % 7;
    d.setUTCDate(d.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const weekNum = 1 + Math.round(((d - firstThursday) / 86400000 - 3 + ((firstThursday.getUTCDay() + 6) % 7)) / 7);
    return `${d.getUTCFullYear()}-W${String(weekNum).padStart(2, '0')}`;
}
function monthKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}
function dayKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function parseEvent(msg) {
    const embed = msg.embeds && msg.embeds[0];
    if (!embed || !embed.description) return null;
    const desc = embed.description;

    const idMatch = (embed.footer && embed.footer.text || '').match(/Id участника:\s*(\d+)/);
    if (!idMatch) return null;
    const userId = idMatch[1];
    const nickMatch = desc.match(/^Участник (.+?) \(/);
    const nick = nickMatch ? nickMatch[1] : userId;
    const at = new Date(msg.createdTimestamp);

    if (desc.includes('покинул голосовой канал')) {
        const ch = desc.match(/<#(\d+)>/);
        if (!ch) return null;
        return { type: 'leave', userId, nick, channelId: ch[1], at };
    }
    if (desc.includes('зашел в голосовой канал')) {
        const ch = desc.match(/<#(\d+)>/);
        if (!ch) return null;
        return { type: 'join', userId, nick, channelId: ch[1], at };
    }
    if (desc.includes('перешел в другой голосовой канал')) {
        const fields = embed.fields || [];
        const toField = fields.find(f => f.name === 'Канал:');
        const fromField = fields.find(f => f.name === 'Предыдущий канал:');
        const to = toField && toField.value.match(/<#(\d+)>/);
        const from = fromField && fromField.value.match(/<#(\d+)>/);
        return { type: 'move', userId, nick, toChannelId: to ? to[1] : null, fromChannelId: from ? from[1] : null, at };
    }
    return null;
}

async function fetchNewMessages(channel, afterId) {
    const all = [];
    let after = afterId;
    for (;;) {
        const batch = await channel.messages.fetch(after ? { after, limit: 100 } : { limit: 100 });
        if (batch.size === 0) break;
        const sorted = [...batch.values()].sort((a, b) => a.createdTimestamp - b.createdTimestamp);
        all.push(...sorted);
        after = sorted[sorted.length - 1].id;
        if (batch.size < 100) break;
        await new Promise(r => setTimeout(r, 300));
    }
    return all;
}

// Тот же парсер, что в check_moder_sync.js — минимально нужно только колонку
// с Discord ID (индекс 2), название секции не важно здесь.
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

// Список ID-кандидатов из той же таблицы, что и сверка (все строки с валидным
// Discord ID, независимо от секции) — не для сверки состава, а просто чтобы
// было что тянуть адресно.
async function fetchSheetCandidateIds() {
    const resp = await fetch(MODER_SHEET_URL);
    if (!resp.ok) throw new Error('Sheets HTTP ' + resp.status);
    const text = await resp.text();
    if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) throw new Error('таблица закрыта');
    const ids = new Set();
    for (const r of parseCsv(text)) {
        const id = (r[2] || '').trim();
        if (/^\d{15,22}$/.test(id)) ids.add(id);
    }
    return [...ids];
}

// guild.members.fetch() без фильтра на этом сервере (135k+ участников) почти
// ни для кого не успевает прогрузить роли (см. комментарий в
// check_moder_sync.js — проверено: 135009 участников, 0 с ролью), поэтому
// именно на нём список модеров получался почти пустым. Основной источник —
// адресная подгрузка по ID из той же гугл-таблицы, что и в сверке (пачками
// по 100, надёжно даже на гигантском сервере); полный скан оставлен только
// как best-effort добавка для тех, кого нет в таблице.
async function fetchModeratorRoster(guild) {
    const roster = {};
    let targetIds = [];
    try {
        targetIds = await fetchSheetCandidateIds();
    } catch (e) {
        console.error('[voice-activity] fetchSheetCandidateIds:', e.message);
    }
    const chunkSize = 100;
    for (let i = 0; i < targetIds.length; i += chunkSize) {
        const chunk = targetIds.slice(i, i + chunkSize);
        try {
            const fetched = await guild.members.fetch({ user: chunk, withPresences: false });
            fetched.forEach(m => { if (m.roles.cache.has(ROLE_ID) && m.id !== SELFBOT_ACCOUNT_ID) roster[m.id] = m.displayName || m.user.username; });
        } catch (e) { /* участник мог выйти с сервера — пропускаем */ }
        await new Promise(r => setTimeout(r, 400));
    }
    try {
        const fetchPromise = guild.members.fetch({ withPresences: false });
        const timeoutPromise = new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), FULL_SCAN_TIMEOUT_MS));
        const all = await Promise.race([fetchPromise, timeoutPromise]);
        all.forEach(m => { if (m.roles.cache.has(ROLE_ID) && m.id !== SELFBOT_ACCOUNT_ID) roster[m.id] = m.displayName || m.user.username; });
    } catch {
        guild.members.cache.forEach(m => { if (m.roles.cache.has(ROLE_ID) && m.id !== SELFBOT_ACCOUNT_ID) roster[m.id] = m.displayName || m.user.username; });
    }
    return roster;
}

function startVoiceActivityWatcher(storePath, onError) {
    onError = onError || (() => {});
    function loadStore() {
        try {
            const d = JSON.parse(fs.readFileSync(storePath, 'utf8'));
            return {
                last_message_id: d.last_message_id || null,
                open_sessions: d.open_sessions || {},
                totals: d.totals || {},
                moderator_roster: d.moderator_roster || {},
            };
        } catch {
            return { last_message_id: null, open_sessions: {}, totals: {}, moderator_roster: {} };
        }
    }
    function saveStore(store) {
        fs.writeFileSync(storePath, JSON.stringify(store, null, 2));
    }
    function closeSession(store, userId, nick, fromMs, toMs) {
        const seconds = Math.round((toMs - fromMs) / 1000);
        if (seconds <= 0) return;
        const atDate = new Date(toMs);
        if (!store.totals[userId]) store.totals[userId] = { nick, weeks: {}, months: {}, days: {}, sessions: [] };
        const t = store.totals[userId];
        if (!t.days) t.days = {};
        if (!t.sessions) t.sessions = [];
        t.nick = nick || t.nick;
        t.weeks[isoWeekKey(atDate)] = (t.weeks[isoWeekKey(atDate)] || 0) + seconds;
        t.months[monthKey(atDate)] = (t.months[monthKey(atDate)] || 0) + seconds;
        t.days[dayKey(atDate)] = (t.days[dayKey(atDate)] || 0) + seconds;
        t.sessions.push({ from: fromMs, to: toMs, seconds });
        if (t.sessions.length > MAX_SESSIONS_PER_USER) t.sessions = t.sessions.slice(-MAX_SESSIONS_PER_USER);
    }

    let roomIds = new Set();
    let roomNameById = new Map();
    let moderatorIds = new Set();

    function applyEvent(store, ev) {
        if (ev.userId === SELFBOT_ACCOUNT_ID || !moderatorIds.has(ev.userId)) return;

        if (ev.type === 'join' && roomIds.has(ev.channelId)) {
            store.open_sessions[ev.userId] = {
                channelId: ev.channelId, channelName: roomNameById.get(ev.channelId) || '',
                since: ev.at.getTime(), nick: ev.nick,
            };
        } else if (ev.type === 'leave') {
            const sess = store.open_sessions[ev.userId];
            if (sess && sess.channelId === ev.channelId) {
                closeSession(store, ev.userId, ev.nick, sess.since, ev.at.getTime());
                delete store.open_sessions[ev.userId];
            }
        } else if (ev.type === 'move') {
            const sess = store.open_sessions[ev.userId];
            if (sess && ev.fromChannelId && sess.channelId === ev.fromChannelId) {
                closeSession(store, ev.userId, ev.nick, sess.since, ev.at.getTime());
                delete store.open_sessions[ev.userId];
            }
            if (ev.toChannelId && roomIds.has(ev.toChannelId)) {
                store.open_sessions[ev.userId] = {
                    channelId: ev.toChannelId, channelName: roomNameById.get(ev.toChannelId) || '',
                    since: ev.at.getTime(), nick: ev.nick,
                };
            }
        }
    }

    async function refreshRooms(guild) {
        await guild.channels.fetch();
        const roomChannels = guild.channels.cache.filter(c => c.parentId === ROOMS_CATEGORY_ID && c.isVoice?.());
        roomIds = new Set(roomChannels.map(c => c.id));
        roomNameById = new Map(roomChannels.map(c => [c.id, c.name]));
    }

    async function refreshRoster(guild) {
        const roster = await fetchModeratorRoster(guild);
        moderatorIds = new Set(Object.keys(roster));
        const store = loadStore();
        store.moderator_roster = roster;
        for (const id of Object.keys(store.open_sessions)) if (!moderatorIds.has(id)) delete store.open_sessions[id];
        for (const id of Object.keys(store.totals)) if (!moderatorIds.has(id)) delete store.totals[id];
        saveStore(store);
    }

    const client = new Client({ checkUpdate: false });

    client.on('ready', async () => {
        try {
            const guild = client.guilds.cache.get(GUILD_ID) || await client.guilds.fetch(GUILD_ID);
            if (!guild) throw new Error('Сервер не найден: ' + GUILD_ID);

            await refreshRooms(guild);
            await refreshRoster(guild);
            setInterval(() => refreshRooms(guild).catch(e => console.error('[voice-activity] refreshRooms:', e.message)), ROOMS_REFRESH_MS);
            setInterval(() => refreshRoster(guild).catch(e => console.error('[voice-activity] refreshRoster:', e.message)), ROSTER_REFRESH_MS);

            // Досчитываем то, что накопилось в логе, пока не были подключены
            // (первый запуск, простой, переподключение).
            const logChannel = await client.channels.fetch(LOG_CHANNEL_ID);
            const store = loadStore();
            const isFirstRun = !store.last_message_id;
            const backlog = isFirstRun
                ? [...(await logChannel.messages.fetch({ limit: 100 })).values()].sort((a, b) => a.createdTimestamp - b.createdTimestamp)
                : await fetchNewMessages(logChannel, store.last_message_id);

            for (const sess of Object.values(store.open_sessions)) {
                if (!sess.channelName && roomNameById.has(sess.channelId)) sess.channelName = roomNameById.get(sess.channelId);
            }
            for (const msg of backlog) {
                const ev = parseEvent(msg);
                if (ev) applyEvent(store, ev);
            }
            if (backlog.length > 0) store.last_message_id = backlog[backlog.length - 1].id;
            store.last_synced_at = Date.now();
            saveStore(store);

            console.log(`[voice-activity] селфбот на связи, разобрано ${backlog.length} сообщений из истории, слежу за логом в реальном времени`);
            onError(null);
        } catch (e) {
            console.error('[voice-activity] ошибка инициализации:', e.message);
            onError(e.message);
        }
    });

    client.on('messageCreate', (msg) => {
        if (msg.channel.id !== LOG_CHANNEL_ID) return;
        const ev = parseEvent(msg);
        if (!ev) return;
        const store = loadStore();
        applyEvent(store, ev);
        store.last_message_id = msg.id;
        store.last_synced_at = Date.now();
        saveStore(store);
    });

    client.on('error', e => { console.error('[voice-activity] client error:', e.message); onError(e.message); });

    client.login(process.env.SELFBOT_TOKEN).catch(e => {
        console.error('[voice-activity] login failed:', e.message);
        onError('login failed: ' + e.message);
    });

    return client;
}

module.exports = { startVoiceActivityWatcher };
