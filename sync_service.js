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
    // разбор ведёт voice_activity_sync.js (селфбот, короткий прогон на
    // каждый вызов, не чаще раза в минуту).
    if (pathname === '/voice-activity' && req.method === 'GET') {
        if (!checkAuth(req, res)) return;

        const storePath = process.env.VOICE_ACTIVITY_JSON_PATH || path.join(ROOT, 'voice_activity.json');
        function loadVoiceStore() {
            try { return JSON.parse(fs.readFileSync(storePath, 'utf8')); } catch { return {}; }
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
        function currentWeekDates() {
            const now = new Date();
            const dayNum = (now.getDay() + 6) % 7; // 0=Пн..6=Вс
            const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dayNum);
            const labels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            return labels.map((label, i) => {
                const d = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i);
                return { key: dayKey(d), label };
            });
        }

        let store = loadVoiceStore();
        const now = Date.now();
        const lastSyncedAt = store.last_synced_at || 0;
        let syncError = null;

        const respond = () => {
            const weekKey = isoWeekKey(new Date());
            const mKey = monthKey(new Date());
            const weekDates = currentWeekDates();
            const totals = store.totals || {};
            const leaderboard = Object.entries(totals).map(([id, t]) => ({
                id, nick: t.nick || id,
                week_seconds: (t.weeks && t.weeks[weekKey]) || 0,
                month_seconds: (t.months && t.months[mKey]) || 0,
                days: weekDates.map(wd => ({ label: wd.label, seconds: (t.days && t.days[wd.key]) || 0 })),
            })).sort((a, b) => b.week_seconds - a.week_seconds);
            const openSessions = store.open_sessions || {};
            const online = Object.entries(openSessions).map(([id, s]) => ({
                id, nick: s.nick || id, channel_name: s.channelName || '', since: s.since,
            })).sort((a, b) => a.since - b.since);
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ leaderboard, online, synced_at: store.last_synced_at || null, sync_error: syncError }));
        };

        if (now - lastSyncedAt > 60000) {
            const scriptPath = path.join(ROOT, 'voice_activity_sync.js');
            execFile('node', [scriptPath], { cwd: ROOT, timeout: 120000, maxBuffer: 20 * 1024 * 1024 }, (err, stdout) => {
                try {
                    const parsed = JSON.parse((stdout || '').trim().split('\n').pop());
                    if (parsed && parsed.error) syncError = parsed.error;
                } catch { /* нет распарсиваемого вывода — не критично */ }
                store = loadVoiceStore();
                store.last_synced_at = now;
                fs.writeFileSync(storePath, JSON.stringify(store, null, 2));
                respond();
            });
        } else {
            respond();
        }
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not found' }));
});

server.listen(PORT, () => console.log(`🔒 Internal sync service on :${PORT}`));
