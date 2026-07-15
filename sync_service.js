// Внутренний Node-сервис только для операций через селфбот (сверка модеров,
// аватарки High staff). Существует ОТДЕЛЬНО от PHP-сайта, потому что для
// discord.js-selfbot-v13 нужен Node.js, которого нет в PHP-контейнере.
// Сайт стучится сюда по приватной сети Railway (не наружу) и проксирует ответ.
//
// Логика внутри — 1:1 то же, что в local-server.js (loadHighStaff,
// loadHighStaffAvatars, check_moder_sync.js), просто вынесено в отдельный
// процесс со своей простой авторизацией по общему секрету вместо cookie-сессий.

try { require('dotenv').config(); } catch {}
const http = require('http');
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');

const PORT = process.env.PORT || 8091;
const ROOT = __dirname;
const INTERNAL_TOKEN = process.env.INTERNAL_SYNC_TOKEN || '';

const HIGH_STAFF_SHEET_URL = 'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
const HIGH_STAFF_HEADERS = {
    'administrator': 'admin',
    'administrative assistant': 'asst',
    'curator': 'curator',
    'master': 'master'
};
const MOD_SECTION_HEADERS = ['moderators', 'список модераторов'];

function normalizeHomoglyphs(s) {
    const map = { 'А':'A','В':'B','Е':'E','К':'K','М':'M','Н':'H','О':'O','Р':'P','С':'C','Т':'T','Х':'X',
                  'а':'a','в':'b','е':'e','к':'k','м':'m','н':'h','о':'o','р':'p','с':'c','т':'t','х':'x' };
    return s.replace(/[А-Яа-я]/g, ch => map[ch] || ch);
}

function parseCsvRows(csvText) {
    const rows = [];
    let row = [], field = '', inQuotes = false;
    for (let i = 0; i < csvText.length; i++) {
        const c = csvText[i];
        if (inQuotes) {
            if (c === '"') { if (csvText[i + 1] === '"') { field += '"'; i++; } else inQuotes = false; }
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

let highStaffCache = { at: 0, byId: new Map() };
const HIGH_STAFF_CACHE_MS = 60000;

async function loadHighStaff() {
    if (highStaffCache.byId.size && Date.now() - highStaffCache.at < HIGH_STAFF_CACHE_MS) {
        return highStaffCache.byId;
    }
    const resp = await fetch(HIGH_STAFF_SHEET_URL);
    if (!resp.ok) throw new Error('Sheets HTTP ' + resp.status);
    const text = await resp.text();
    if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
        throw new Error('Таблица закрыта — открой доступ «по ссылке → Читатель».');
    }
    const rows = parseCsvRows(text);
    const byId = new Map();
    let currentRole = null;
    for (const r of rows) {
        if (!r) continue;
        const cellTexts = r.map(c => normalizeHomoglyphs((c || '').trim()).toLowerCase());
        if (cellTexts.some(t => MOD_SECTION_HEADERS.includes(t))) break;
        const headerCell = cellTexts.find(t => HIGH_STAFF_HEADERS[t]);
        if (headerCell) { currentRole = HIGH_STAFF_HEADERS[headerCell]; continue; }
        if (!currentRole) continue;
        const id = (r[2] || '').trim();
        if (!/^\d{15,22}$/.test(id)) continue;
        const date = (r[1] || '').trim();
        const nick = (r[3] || '').trim();
        const days = (r[4] || '').trim();
        const name = (r[5] || '').trim();
        byId.set(id, { nick, name, date, days, role: currentRole });
    }
    highStaffCache = { at: Date.now(), byId };
    return byId;
}

function execFileP(cmd, args, opts) {
    return new Promise((resolve, reject) => {
        execFile(cmd, args, opts, (err, stdout) => {
            if (err && !stdout) return reject(err);
            resolve(stdout);
        });
    });
}

let highStaffAvatarCache = { at: 0, byId: {} };
const HIGH_STAFF_AVATAR_CACHE_MS = 10 * 60 * 1000;
let highStaffAvatarInFlight = null;

async function loadHighStaffAvatars(ids) {
    const missing = ids.filter(id => !(id in highStaffAvatarCache.byId));
    const stale = Date.now() - highStaffAvatarCache.at > HIGH_STAFF_AVATAR_CACHE_MS;
    if (!missing.length && !stale) return highStaffAvatarCache.byId;
    if (highStaffAvatarInFlight) return highStaffAvatarInFlight;

    highStaffAvatarInFlight = (async () => {
        try {
            const scriptPath = path.join(ROOT, 'fetch_high_staff_avatars.js');
            const stdout = await execFileP('node', [scriptPath, JSON.stringify(ids)], { cwd: ROOT, timeout: 60000 });
            const fresh = JSON.parse(stdout.trim().split('\n').pop());
            highStaffAvatarCache = { at: Date.now(), byId: { ...highStaffAvatarCache.byId, ...fresh } };
        } catch (e) {
            console.error('high staff avatars fetch err:', e.message);
        } finally {
            highStaffAvatarInFlight = null;
        }
        return highStaffAvatarCache.byId;
    })();
    return highStaffAvatarInFlight;
}

function checkAuth(req, res) {
    if (!INTERNAL_TOKEN || req.headers['x-internal-token'] !== INTERNAL_TOKEN) {
        res.writeHead(401, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'unauthorized' }));
        return false;
    }
    return true;
}

// === Активность в голосовых "Комнатах" ===
const VOICE_ACTIVITY_PATH = process.env.VOICE_ACTIVITY_JSON_PATH || path.join(ROOT, 'voice_activity.json');
const VOICE_SYNC_SCRIPT = path.join(ROOT, 'voice_activity_sync.js');
const VOICE_SYNC_INTERVAL_MS = Number(process.env.VOICE_SYNC_INTERVAL_MS || 5 * 60 * 1000);
const VOICE_SYNC_MIN_GAP_MS = 60000; // не чаще раза в минуту по запросу с сайта
let voiceSyncInFlight = null;
let voiceLastSyncError = null;

function loadVoiceStore() {
    try { return JSON.parse(fs.readFileSync(VOICE_ACTIVITY_PATH, 'utf8')); } catch { return {}; }
}
function voiceIsoWeekKey(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = (d.getUTCDay() + 6) % 7;
    d.setUTCDate(d.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const weekNum = 1 + Math.round(((d - firstThursday) / 86400000 - 3 + ((firstThursday.getUTCDay() + 6) % 7)) / 7);
    return `${d.getUTCFullYear()}-W${String(weekNum).padStart(2, '0')}`;
}
function voiceMonthKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}
function voiceDayKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function voiceCurrentWeekDates() {
    const now = new Date();
    const dayNum = (now.getDay() + 6) % 7; // 0=Пн..6=Вс
    const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dayNum);
    const labels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const fullLabels = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    return labels.map((label, i) => {
        const d = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i);
        const date = String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0') + '.' + d.getFullYear();
        return { key: voiceDayKey(d), label, full_label: fullLabels[i], date };
    });
}
function voiceClock(ms) {
    const d = new Date(ms);
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}
function voiceDuration(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? `${m} м ${s} с` : `${s} с`;
}
// Формат "16 мин. 30 сек." / "0 сек." — как в дневной сводке.
function voiceDayDuration(seconds) {
    if (!seconds) return '0 сек.';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const parts = [];
    if (h > 0) parts.push(h + ' ч.');
    if (m > 0) parts.push(m + ' мин.');
    if (s > 0 || parts.length === 0) parts.push(s + ' сек.');
    return parts.join(' ');
}

// Запускает короткий прогон селфбота (login → разбор новых сообщений лога →
// logout), не чаще раза в минуту, и не параллельно самому себе.
function runVoiceActivitySync(callback) {
    const store = loadVoiceStore();
    const now = Date.now();
    if (voiceSyncInFlight) { voiceSyncInFlight.then(callback); return; }
    if (now - (store.last_synced_at || 0) < VOICE_SYNC_MIN_GAP_MS) { callback(); return; }

    voiceSyncInFlight = new Promise(resolve => {
        execFile('node', [VOICE_SYNC_SCRIPT], { cwd: ROOT, timeout: 120000, maxBuffer: 20 * 1024 * 1024 }, (err, stdout) => {
            voiceLastSyncError = null;
            try {
                const parsed = JSON.parse((stdout || '').trim().split('\n').pop());
                if (parsed && parsed.error) voiceLastSyncError = parsed.error;
            } catch { /* нет распарсиваемого вывода — не критично */ }
            const fresh = loadVoiceStore();
            fresh.last_synced_at = Date.now();
            fs.writeFileSync(VOICE_ACTIVITY_PATH, JSON.stringify(fresh, null, 2));
            voiceSyncInFlight = null;
            resolve();
        });
    });
    voiceSyncInFlight.then(callback);
}

function buildVoiceActivityResponse() {
    const store = loadVoiceStore();
    const weekKey = voiceIsoWeekKey(new Date());
    const mKey = voiceMonthKey(new Date());
    const weekDates = voiceCurrentWeekDates();
    const todayKey = voiceDayKey(new Date());
    const totals = store.totals || {};
    const roster = store.moderator_roster || {};
    // Полный список текущих модеров, а не только тех, у кого уже была
    // активность — иначе на виду только занятые, а те, кто вообще не
    // заходил, незаметны.
    const ids = new Set([...Object.keys(roster), ...Object.keys(totals)]);
    const leaderboard = [...ids].map(id => {
        const t = totals[id] || {};
        const sessionsToday = (t.sessions || [])
            .filter(s => voiceDayKey(new Date(s.from)) === todayKey)
            .map(s => ({ from: voiceClock(s.from), to: voiceClock(s.to), duration: voiceDuration(s.seconds) }));
        return {
            id, nick: roster[id] || t.nick || id,
            week_seconds: (t.weeks && t.weeks[weekKey]) || 0,
            month_seconds: (t.months && t.months[mKey]) || 0,
            days: weekDates.map(wd => {
                const seconds = (t.days && t.days[wd.key]) || 0;
                return { label: wd.label, full_label: wd.full_label, date: wd.date, seconds, duration: voiceDayDuration(seconds) };
            }),
            sessions_today: sessionsToday,
        };
    }).sort((a, b) => b.week_seconds - a.week_seconds);
    const openSessions = store.open_sessions || {};
    const online = Object.entries(openSessions).map(([id, s]) => ({
        id, nick: s.nick || id, channel_name: s.channelName || '', since: s.since,
    })).sort((a, b) => a.since - b.since);
    return { leaderboard, online, synced_at: store.last_synced_at || null, sync_error: voiceLastSyncError };
}

// Крутим синк по таймеру независимо от запросов с сайта — данные копятся
// сами, а не только когда кто-то открыл вкладку "Активность".
setInterval(() => runVoiceActivitySync(() => {}), VOICE_SYNC_INTERVAL_MS);
runVoiceActivitySync(() => {});

const server = http.createServer(async (req, res) => {
    const u = new URL(req.url, `http://${req.headers.host}`);
    const pathname = u.pathname;

    if (pathname === '/health') {
        res.writeHead(200, { 'Content-Type': 'text/plain' });
        res.end('ok');
        return;
    }

    if (pathname === '/sync-moderators' && req.method === 'GET') {
        if (!checkAuth(req, res)) return;
        const scriptPath = path.join(ROOT, 'check_moder_sync.js');
        execFile('node', [scriptPath], { cwd: ROOT, timeout: 120000, maxBuffer: 20 * 1024 * 1024 }, (err, stdout, stderr) => {
            if (err && !stdout) {
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'sync script failed: ' + err.message + (stderr ? ' | ' + stderr.slice(0, 500) : '') }));
                return;
            }
            let result;
            try {
                result = JSON.parse(stdout.trim().split('\n').pop());
            } catch (e) {
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'bad sync output: ' + e.message }));
                return;
            }
            res.writeHead(result.error ? 500 : 200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify(result));
        });
        return;
    }

    if (pathname === '/high-staff' && req.method === 'GET') {
        if (!checkAuth(req, res)) return;
        try {
            const byId = await loadHighStaff();
            const roster = { admin: [], asst: [], chief: [], curator: [], master: [] };
            const allIds = [];
            for (const [id, info] of byId) {
                if (!roster[info.role]) continue;
                allIds.push(id);
                roster[info.role].push({ id, name: info.name, nick: info.nick, date: info.date, days: info.days });
            }
            const avatars = await loadHighStaffAvatars(allIds);
            for (const role of Object.keys(roster)) {
                roster[role].forEach(m => { m.avatar = avatars[m.id] || null; });
            }
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ updated_at: new Date(highStaffCache.at).toISOString(), roster }));
        } catch (e) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
        return;
    }

    // GET /high-staff/avatar?id=... — аватарка одного человека (для шапки/профиля сайта)
    if (pathname === '/high-staff/avatar' && req.method === 'GET') {
        if (!checkAuth(req, res)) return;
        const id = u.searchParams.get('id');
        if (!id) { res.writeHead(400, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ error: 'id required' })); return; }
        try {
            const avatars = await loadHighStaffAvatars([id]);
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ avatar: avatars[id] || null }));
        } catch (e) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
        return;
    }

    // Активность в голосовых "Комнатах" — накопленное время за неделю/месяц,
    // разбор ведёт voice_activity_sync.js (селфбот, короткий прогон). Помимо
    // отклика на запрос сайта, тот же прогон крутится по таймеру (см. низ
    // файла), чтобы данные копились сами, а не только когда кто-то открыл вкладку.
    if (pathname === '/voice-activity' && req.method === 'GET') {
        if (!checkAuth(req, res)) return;
        runVoiceActivitySync(() => {
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify(buildVoiceActivityResponse()));
        });
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not found' }));
});

server.listen(PORT, () => console.log(`🔒 Internal sync service on :${PORT}`));
