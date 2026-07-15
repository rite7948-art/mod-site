// Трекер активности в голосовых "Комнатах" — по образцу check_moder_sync.js.
// Своего голосового наблюдения не ведём (это был бы риск постоянного
// подключения селфбота к шлюзу) — вместо этого читаем уже существующий
// текстовый канал-лог входов/выходов (его ведёт другой бот) и на его основе
// считаем время каждого человека в комнатах за неделю/месяц.
//
// Логинимся селфботом только на время одного прогона: подтягиваем список
// комнат нужной категории (могут пересоздаваться — поэтому не хардкодим ID),
// забираем новые сообщения из лога начиная с last_message_id, разбираем и
// обновляем voice_activity.json. Запуск: node voice_activity_sync.js

require('dotenv').config();
const fs = require('fs');
const path = require('path');
const { Client } = require('discord.js-selfbot-v13');

const GUILD_ID = process.env.MODER_GUILD_ID || '531970658633252864';
const ROLE_ID = process.env.MODER_ROLE_ID || '1148609467257208904';
const ROOMS_CATEGORY_ID = process.env.VOICE_ROOMS_CATEGORY_ID || '965250253873889310';
const LOG_CHANNEL_ID = process.env.VOICE_LOG_CHANNEL_ID || '965269054321471530';
const STORE_PATH = process.env.VOICE_ACTIVITY_JSON_PATH || path.join(__dirname, 'voice_activity.json');
const SELFBOT_ACCOUNT_ID = process.env.SELFBOT_ACCOUNT_ID || '1520428688255222008';
const FULL_SCAN_TIMEOUT_MS = Number(process.env.VOICE_FULL_SCAN_TIMEOUT_MS || 45000);

function loadStore() {
    try {
        const d = JSON.parse(fs.readFileSync(STORE_PATH, 'utf8'));
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
    fs.writeFileSync(STORE_PATH, JSON.stringify(store, null, 2));
}

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

function addSeconds(store, userId, nick, seconds, atDate) {
    if (seconds <= 0) return;
    if (!store.totals[userId]) store.totals[userId] = { nick, weeks: {}, months: {}, days: {} };
    const t = store.totals[userId];
    if (!t.days) t.days = {};
    t.nick = nick || t.nick;
    const wk = isoWeekKey(atDate);
    const mk = monthKey(atDate);
    const dk = dayKey(atDate);
    t.weeks[wk] = (t.weeks[wk] || 0) + seconds;
    t.months[mk] = (t.months[mk] || 0) + seconds;
    t.days[dk] = (t.days[dk] || 0) + seconds;
}

// Best-effort полный список текущих держателей роли Модератора — нужен,
// чтобы в лидерборде было видно и тех, у кого активности за период вообще
// не было (не только тех, кто уже закрыл хоть одну сессию). На гигантском
// сервере скан может быть неполным — тот же компромисс, что в
// check_moder_sync.js для сверки таблицы.
async function fetchModeratorRoster(guild) {
    const roster = {};
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

const client = new Client({ checkUpdate: false });

client.on('ready', async () => {
    try {
        const guild = client.guilds.cache.get(GUILD_ID) || await client.guilds.fetch(GUILD_ID);
        if (!guild) throw new Error('Сервер не найден: ' + GUILD_ID);

        // Список "Комнат" текущей категории — не хардкодим ID на случай
        // пересоздания каналов, тянем свежим на каждый прогон.
        await guild.channels.fetch();
        const roomChannels = guild.channels.cache.filter(c => c.parentId === ROOMS_CATEGORY_ID && c.isVoice?.());
        const roomIds = new Set(roomChannels.map(c => c.id));
        const roomNameById = new Map(roomChannels.map(c => [c.id, c.name]));

        const logChannel = await client.channels.fetch(LOG_CHANNEL_ID);
        const store = loadStore();
        const isFirstRun = !store.last_message_id;

        // Досчитываем название комнаты для сессий, открытых до того, как оно
        // стало частью формата хранения — иначе так и останутся пустыми.
        for (const sess of Object.values(store.open_sessions)) {
            if (!sess.channelName && roomNameById.has(sess.channelId)) {
                sess.channelName = roomNameById.get(sess.channelId);
            }
        }

        const messages = isFirstRun
            ? [...(await logChannel.messages.fetch({ limit: 100 })).values()].sort((a, b) => a.createdTimestamp - b.createdTimestamp)
            : await fetchNewMessages(logChannel, store.last_message_id);

        const events = messages.map(parseEvent).filter(ev => ev && ev.userId !== SELFBOT_ACCOUNT_ID);

        // Полный (best-effort) список текущих Модераторов — источник истины
        // и для фильтрации событий, и для отображения тех, у кого активности
        // ещё не было вовсе.
        const moderatorRoster = await fetchModeratorRoster(guild);
        store.moderator_roster = moderatorRoster;
        const moderatorIds = new Set(Object.keys(moderatorRoster));

        for (const id of Object.keys(store.open_sessions)) {
            if (!moderatorIds.has(id)) delete store.open_sessions[id];
        }
        for (const id of Object.keys(store.totals)) {
            if (!moderatorIds.has(id)) delete store.totals[id];
        }

        for (const ev of events) {
            if (!moderatorIds.has(ev.userId)) continue;

            if (ev.type === 'join' && roomIds.has(ev.channelId)) {
                store.open_sessions[ev.userId] = {
                    channelId: ev.channelId, channelName: roomNameById.get(ev.channelId) || '',
                    since: ev.at.getTime(), nick: ev.nick,
                };
            } else if (ev.type === 'leave') {
                const sess = store.open_sessions[ev.userId];
                if (sess && sess.channelId === ev.channelId) {
                    addSeconds(store, ev.userId, ev.nick, Math.round((ev.at.getTime() - sess.since) / 1000), ev.at);
                    delete store.open_sessions[ev.userId];
                }
            } else if (ev.type === 'move') {
                const sess = store.open_sessions[ev.userId];
                if (sess && ev.fromChannelId && sess.channelId === ev.fromChannelId) {
                    addSeconds(store, ev.userId, ev.nick, Math.round((ev.at.getTime() - sess.since) / 1000), ev.at);
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

        if (messages.length > 0) store.last_message_id = messages[messages.length - 1].id;
        saveStore(store);

        console.log(JSON.stringify({ ok: true, processed: messages.length, tracked_rooms: roomIds.size, moderators: moderatorIds.size }));
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
