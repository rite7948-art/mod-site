// Локальный Node-сервер, имитирующий PHP-поведение сайта.
// Использует users.json (тот же, куда пишет бот).
// Запуск: node local-server.js

try { require('dotenv').config(); } catch {}
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFile } = require('child_process');

const PORT = 8090;
const ROOT = __dirname;
const USERS_PATH = process.env.USERS_JSON_PATH || path.join(ROOT, 'users.json');
const ROSTER_PATH = process.env.ROSTER_JSON_PATH || path.join(ROOT, 'roster.json');
const WARN_PATH = process.env.WARNINGS_JSON_PATH || path.join(ROOT, 'warnings.json');
const EVENTS_PATH = process.env.EVENTS_JSON_PATH || path.join(ROOT, 'events.json');
const LEVELUP_PATH = process.env.LEVELUP_JSON_PATH || path.join(ROOT, 'levelup.json');
const PROFILES_PATH = process.env.PROFILES_JSON_PATH || path.join(ROOT, 'profiles.json');
const EMBEDS_LOG_PATH = process.env.EMBEDS_LOG_JSON_PATH || path.join(ROOT, 'embeds_log.json');
const EMBEDS_LOG_MAX = 30;

function loadProfiles() {
    try {
        if (!fs.existsSync(PROFILES_PATH)) return {};
        return JSON.parse(fs.readFileSync(PROFILES_PATH, 'utf8') || '{}');
    } catch { return {}; }
}
function saveProfilesFile(data) {
    fs.writeFileSync(PROFILES_PATH, JSON.stringify(data, null, 2));
}

function loadEmbedsLog() {
    try {
        if (!fs.existsSync(EMBEDS_LOG_PATH)) return { next_id: 1, items: [] };
        const d = JSON.parse(fs.readFileSync(EMBEDS_LOG_PATH, 'utf8'));
        return { next_id: d.next_id || 1, items: Array.isArray(d.items) ? d.items : [] };
    } catch { return { next_id: 1, items: [] }; }
}
function appendEmbedsLog(entry) {
    const data = loadEmbedsLog();
    entry.id = data.next_id++;
    data.items.push(entry);
    if (data.items.length > EMBEDS_LOG_MAX) data.items = data.items.slice(-EMBEDS_LOG_MAX);
    fs.writeFileSync(EMBEDS_LOG_PATH, JSON.stringify(data, null, 2));
}

function loadLevelup() {
    try {
        if (!fs.existsSync(LEVELUP_PATH)) return { next_id: 1, items: [] };
        const d = JSON.parse(fs.readFileSync(LEVELUP_PATH, 'utf8'));
        return { next_id: d.next_id || 1, items: Array.isArray(d.items) ? d.items : [] };
    } catch { return { next_id: 1, items: [] }; }
}
function saveLevelupFile(data) {
    fs.writeFileSync(LEVELUP_PATH, JSON.stringify(data, null, 2));
}
const LEVELUP_MAX = 10;

function loadEvents() {
    try {
        if (!fs.existsSync(EVENTS_PATH)) return { next_id: 1, items: [] };
        const d = JSON.parse(fs.readFileSync(EVENTS_PATH, 'utf8'));
        return { next_id: d.next_id || 1, items: Array.isArray(d.items) ? d.items : [] };
    } catch { return { next_id: 1, items: [] }; }
}
function saveEventsFile(data) {
    fs.writeFileSync(EVENTS_PATH, JSON.stringify(data, null, 2));
}

function loadWarnings() {
    try {
        if (!fs.existsSync(WARN_PATH)) return { next_id: 1, items: [] };
        const d = JSON.parse(fs.readFileSync(WARN_PATH, 'utf8'));
        return { next_id: d.next_id || 1, items: Array.isArray(d.items) ? d.items : [] };
    } catch { return { next_id: 1, items: [] }; }
}
function saveWarningsFile(data) {
    fs.writeFileSync(WARN_PATH, JSON.stringify(data, null, 2));
}
function isExpired(w) {
    return w.expires_at && new Date(w.expires_at) <= new Date();
}
function warnStatus(w) {
    if (w.justified_at) return 'justified';
    if (isExpired(w)) return 'expired';
    return 'active';
}
function readJsonBody(req) {
    return new Promise(resolve => {
        let d = '';
        req.on('data', c => d += c);
        req.on('end', () => { try { resolve(JSON.parse(d || '{}')); } catch { resolve({}); } });
    });
}
function roleLevel(role) {
    return { master: 1, curator: 2, chief: 3, asst: 4, admin: 4 }[role] || 0;
}

// Discord OAuth
const DISCORD_CLIENT_ID     = process.env.DISCORD_CLIENT_ID     || '';
const DISCORD_CLIENT_SECRET = process.env.DISCORD_CLIENT_SECRET || '';
const DISCORD_REDIRECT_URI  = process.env.DISCORD_REDIRECT_URI  || `http://localhost:${PORT}/auth/discord/callback`;

// === Вход через Discord сверяется с секцией "High staff" гугл-таблицы ===
// (та же таблица/лист, что и сверка модеров — просто верхняя секция вместо
// нижней). Роль на сайте берётся из заголовка подраздела: Administrator,
// Administrative Assistant, Curator, Master.
const HIGH_STAFF_SHEET_URL = 'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
const HIGH_STAFF_HEADERS = {
    'administrator': 'admin',
    'administrative assistant': 'asst',
    'curator': 'curator',
    'master': 'master'
};
const MOD_SECTION_HEADERS = ['moderators', 'список модераторов'];

// В таблице заголовок "Аdministrative Аssistant" кто-то набрал с кириллическими
// «А» вместо латинских — визуально не отличить, но строкой не совпадает.
// Нормализуем похожие кириллические буквы в латиницу перед сравнением.
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
    const byId = new Map(); // id -> { nick, name, date, days, role }
    let currentRole = null;
    for (const r of rows) {
        if (!r) continue;
        const cellTexts = r.map(c => normalizeHomoglyphs((c || '').trim()).toLowerCase());
        if (cellTexts.some(t => MOD_SECTION_HEADERS.includes(t))) break; // дальше идут модеры, не вышка
        const headerCell = cellTexts.find(t => HIGH_STAFF_HEADERS[t]);
        if (headerCell) { currentRole = HIGH_STAFF_HEADERS[headerCell]; continue; }
        if (!currentRole) continue;
        const id = (r[2] || '').trim();
        if (!/^\d{15,22}$/.test(id)) continue;
        // Колонки: B=дата, C=айди, D=ник, E=дни, F=имя (та же раскладка, что у выговоров)
        const date = (r[1] || '').trim();
        const nick = (r[3] || '').trim();
        const days = (r[4] || '').trim();
        const name = (r[5] || '').trim();
        byId.set(id, { nick, name, date, days, role: currentRole });
    }
    highStaffCache = { at: Date.now(), byId };
    return byId;
}

// ID из этого списка всегда логинятся как admin, даже если их нет в таблице
// (или таблица временно недоступна) — на случай пробелов/лагов в High staff.
const SUPER_ADMIN_IDS = (process.env.SUPER_ADMIN_IDS || '').split(',').map(s => s.trim()).filter(Boolean);

async function findInHighStaff(discordId) {
    const id = String(discordId);
    try {
        const byId = await loadHighStaff();
        const found = byId.get(id);
        if (SUPER_ADMIN_IDS.includes(id)) return { username: found ? found.nick : null, role: 'admin' };
        if (!found) return null;
        return { username: found.nick, role: found.role };
    } catch (e) {
        console.error('high staff sheet read err:', e.message);
        if (SUPER_ADMIN_IDS.includes(id)) return { username: null, role: 'admin' };
        return null;
    }
}

// Аватарки для карточек High staff — точечно тянутся селфботом
// (fetch_high_staff_avatars.js), кэшируются на 10 минут, чтобы не логиниться
// в Discord при каждом открытии вкладки "Главная".
let highStaffAvatarCache = { at: 0, byId: {} };
const HIGH_STAFF_AVATAR_CACHE_MS = 10 * 60 * 1000;

function execFileP(cmd, args, opts) {
    return new Promise((resolve, reject) => {
        execFile(cmd, args, opts, (err, stdout) => {
            if (err && !stdout) return reject(err);
            resolve(stdout);
        });
    });
}

// Главная и Выговоры дёргают /api/high-staff почти одновременно при загрузке
// страницы — без блокировки это запускало ДВА параллельных логина селфбота
// одним и тем же токеном, и Discord обрывал одну из сессий (аватарки молча
// пропадали). Однополётный (single-flight) промис: все, кто подоспел, пока
// идёт логин, ждут один и тот же результат вместо второго логина.
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

// Синхронизация выговоров в Google Sheets через Apps Script webhook
const SHEETS_WEBHOOK_URL   = process.env.SHEETS_WEBHOOK_URL   || '';
const SHEETS_WEBHOOK_TOKEN = process.env.SHEETS_WEBHOOK_TOKEN || '';

function countActiveFor(items, discordId) {
    return items.filter(w => w.target_id === discordId && warnStatus(w) === 'active').length;
}
async function syncWarningToSheet(discordId, count) {
    if (!SHEETS_WEBHOOK_URL) return;
    try {
        await fetch(SHEETS_WEBHOOK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: SHEETS_WEBHOOK_TOKEN,
                action: 'update_warning',
                discord_id: String(discordId),
                count: Math.min(3, +count || 0)
            }),
            redirect: 'follow'
        });
    } catch (e) {
        console.error('Sheets sync failed:', e.message);
    }
}

const ROLE_NAMES = {
    master:  'Мастер',
    curator: 'Куратор',
    chief:   'Главный куратор',
    admin:   'Администратор'
};
const TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.css':  'text/css; charset=utf-8',
    '.js':   'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.png':  'image/png',
    '.jpg':  'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.gif':  'image/gif',
    '.svg':  'image/svg+xml',
    '.ico':  'image/x-icon'
};

// Сессии в памяти: sid -> { username, role, discord_id }
const sessions = new Map();

function parseCookies(req) {
    const h = req.headers.cookie || '';
    const out = {};
    h.split(';').forEach(pair => {
        const [k, ...v] = pair.trim().split('=');
        if (k) out[k] = decodeURIComponent(v.join('='));
    });
    return out;
}
function currentUser(req) {
    const sid = parseCookies(req).sid;
    return sid ? sessions.get(sid) : null;
}
function readBody(req) {
    return new Promise(resolve => {
        let d = '';
        req.on('data', c => d += c);
        req.on('end', () => resolve(d));
    });
}
function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// === Страницы ===

function renderLogin(error = '') {
    return `<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Futurama Moderator</title>
    <link rel="icon" type="image/webp" href="/logo.webp">
    <link rel="stylesheet" href="/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-locked">
    <div class="auth-screen show">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="/logo.webp" alt="" class="auth-logo-img">
                <div>
                    <div class="auth-brand">Futurama</div>
                    <div class="auth-brand-sub">Moderator</div>
                </div>
            </div>
            <h2 class="auth-title">Вход в панель</h2>

            ${error ? `<div class="auth-error show">${escapeHtml(error)}</div>` : ''}

            <a href="/auth/discord" class="auth-discord-btn">
                <i class="fab fa-discord"></i> Войти через Discord
            </a>
        </div>
    </div>
</body>
</html>`;
}

async function renderIndex(user) {
    let html = fs.readFileSync(path.join(ROOT, 'index.php'), 'utf8');

    // Удаляем PHP-блок наверху
    html = html.replace(/^<\?php[\s\S]*?\?>\s*/m, '');

    // Подставляем имя/инициал (или аватарку, если найдётся по discord_id) в карточку юзера
    const initial = (user.username || '?').charAt(0).toUpperCase();
    const roleName = ROLE_NAMES[user.role] || 'Без роли';
    let avatarUrl = null;
    if (user.discord_id) {
        try {
            const avatars = await loadHighStaffAvatars([user.discord_id]);
            avatarUrl = avatars[user.discord_id] || null;
        } catch {}
    }
    const avatarHtml = avatarUrl
        ? `<img class="avatar-circle" src="${escapeHtml(avatarUrl)}" style="object-fit:cover;" alt="">`
        : `<div class="avatar-circle">${escapeHtml(initial)}</div>`;
    html = html.replace(
        /<div class="avatar-circle">[\s\S]*?<\/div>\s*<div style="overflow:hidden;">\s*<div class="u-name">[\s\S]*?<\/div>\s*<div class="u-role[\s\S]*?<\/div>\s*<\/div>/,
        `${avatarHtml}
                    <div style="overflow:hidden;">
                        <div class="u-name">${escapeHtml(user.username)}</div>
                        <div class="u-role role-${escapeHtml(user.role)}">${escapeHtml(roleName)}</div>
                    </div>`
    );

    // Подменяем JSON-инжект CURRENT_USER (PHP-плейсхолдер <?= json_encode($me) ?>)
    const userJson = JSON.stringify({
        username: user.username,
        role: user.role,
        role_name: roleName,
        discord_id: user.discord_id || ''
    });
    html = html.replace(/const CURRENT_USER = <\?= json_encode\(\$me, JSON_UNESCAPED_UNICODE\) \?>;/,
        `const CURRENT_USER = ${userJson};`);

    return html;
}

// === HTTP-роутер ===

const server = http.createServer(async (req, res) => {
    const u = new URL(req.url, `http://${req.headers.host}`);
    const pathname = u.pathname;

    // GET /api/roster — отдаёт состав вышки из roster.json (его пишет бот)
    if (pathname === '/api/roster') {
        if (!currentUser(req)) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }
        if (!fs.existsSync(ROSTER_PATH)) {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ updated_at: null, roster: { admin: [], asst: [], chief: [], curator: [], master: [] }, error: 'roster.json пока не создан — запусти бота и убедись, что Server Members Intent включён' }));
            return;
        }
        try {
            const data = fs.readFileSync(ROSTER_PATH, 'utf8');
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(data);
        } catch (e) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
        return;
    }

    // GET /api/high-staff — весь состав вышки из секции High staff гугл-таблицы
    // (та же таблица, что сверяется при входе через Discord). Актуальнее, чем
    // /api/roster — та читает Discord-роли с сервера, на котором вышка сейчас
    // фактически не размечена ролями.
    if (pathname === '/api/high-staff.php') {
        if (!currentUser(req)) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }
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
            const profiles = loadProfiles();
            for (const role of Object.keys(roster)) {
                roster[role].forEach(m => {
                    m.avatar = avatars[m.id] || null;
                    m.banner = (profiles[m.id] && profiles[m.id].banner) || null;
                });
            }
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ updated_at: new Date(highStaffCache.at).toISOString(), roster }));
        } catch (e) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
        return;
    }

    // POST /api/save-banner.php — баннер профиля, виден всем в "Составе вышки"
    if (pathname === '/api/save-banner.php' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (!user.discord_id) { res.writeHead(400); res.end(JSON.stringify({ error: 'no discord_id' })); return; }
        const body = await readJsonBody(req);
        const banner = String(body.banner || '').trim();
        if (banner.length > 2 * 1024 * 1024) {
            res.writeHead(400); res.end(JSON.stringify({ error: 'banner too large' })); return;
        }
        if (banner && !/^(data:image\/|https?:\/\/)/i.test(banner)) {
            res.writeHead(400); res.end(JSON.stringify({ error: 'bad banner format' })); return;
        }
        const profiles = loadProfiles();
        if (!banner) delete profiles[user.discord_id];
        else profiles[user.discord_id] = { banner, updated_at: new Date().toISOString() };
        saveProfilesFile(profiles);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
        return;
    }

    // === Сверка таблицы модеров ===
    // Логика 1:1 по образцу футика2 (check_sync.js, запускается по требованию):
    // check_moder_sync.js сам логинится селфботом, читает таблицу и сверяет
    // с ролью Модератора на живых данных — вместо чтения устаревшего снапшота.
    if (pathname === '/api/sync-moderators.php' && req.method === 'GET') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }

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
            if (result.error) {
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify(result));
                return;
            }
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify(result));
        });
        return;
    }

    // === Эмбиты в инфо-каналы (curator+) ===
    // Каналы жёстко зашиты списком — клиент выбирает только ключ ('master'/
    // 'curator'), не сырой channel_id, чтобы нельзя было запостить в чужой канал.
    if (pathname === '/api/send-embed.php' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (roleLevel(user.role) < 2) { res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return; }

        const EMBED_CHANNELS = {
            master: '1510992131446018139',
            curator: '1510992164392538163'
        };
        const body = await readJsonBody(req);
        const channelId = EMBED_CHANNELS[body.channel];
        if (!channelId) { res.writeHead(400); res.end(JSON.stringify({ error: 'bad channel' })); return; }

        const title = String(body.title || '').slice(0, 256);
        const description = String(body.description || '').slice(0, 4096);
        const image = String(body.image || '').trim();
        if (!title && !description) { res.writeHead(400); res.end(JSON.stringify({ error: 'empty embed' })); return; }

        let color = 0xe5352b;
        if (/^#[0-9a-fA-F]{6}$/.test(body.color || '')) color = parseInt(body.color.slice(1), 16);

        const embed = { color };
        if (title) embed.title = title;
        if (description) embed.description = description;
        if (image && /^https?:\/\//.test(image)) embed.image = { url: image };
        embed.footer = { text: 'Опубликовал ' + (user.username || '?') };
        embed.timestamp = new Date().toISOString();

        try {
            const discordToken = process.env.DISCORD_TOKEN || '';
            if (!discordToken) throw new Error('DISCORD_TOKEN не настроен');
            const resp = await fetch(`https://discord.com/api/v10/channels/${channelId}/messages`, {
                method: 'POST',
                headers: { 'Authorization': 'Bot ' + discordToken, 'Content-Type': 'application/json' },
                body: JSON.stringify({ embeds: [embed] })
            });
            if (!resp.ok) {
                const errText = await resp.text();
                throw new Error('Discord HTTP ' + resp.status + ': ' + errText.slice(0, 300));
            }
            appendEmbedsLog({
                channel: body.channel,
                title, description, image, color: body.color || '#e5352b',
                sent_by: user.username || '',
                created_at: new Date().toISOString(),
            });
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true }));
        } catch (e) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
        return;
    }

    // История последних отправленных эмбитов (для вкладки "Эмбиты")
    if (pathname === '/api/embeds-log.php' && req.method === 'GET') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (roleLevel(user.role) < 2) { res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return; }
        const data = loadEmbedsLog();
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ items: data.items.slice().reverse() }));
        return;
    }

    // === Выговоры ===
    if (pathname === '/api/warnings.php') {
        const user = currentUser(req);
        if (!user) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }

        // GET — список
        if (req.method === 'GET') {
            const data = loadWarnings();
            const items = data.items.map(w => ({ ...w, status: warnStatus(w) }));
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ items }));
            return;
        }

        // POST — выдать (admin/asst — всем; curator — только мастерам)
        if (req.method === 'POST') {
            const body = await readJsonBody(req);
            const required = ['target_id', 'target_nick', 'target_category', 'reason', 'duration_days'];
            for (const k of required) {
                if (!body[k] && body[k] !== 0) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: 'missing field: ' + k }));
                    return;
                }
            }
            const myLevel = roleLevel(user.role);
            const canIssueHere = myLevel >= 3 || (myLevel === 2 && body.target_category === 'master');
            if (!canIssueHere) {
                res.writeHead(403, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'forbidden' }));
                return;
            }
            const data = loadWarnings();
            const now = new Date();
            const expires = new Date(now.getTime() + (+body.duration_days) * 86400 * 1000);
            const item = {
                id: data.next_id++,
                target_id: String(body.target_id || ''),
                target_nick: String(body.target_nick || ''),
                target_name: String(body.target_name || ''),
                target_category: String(body.target_category || 'master'),
                reason: String(body.reason || '').slice(0, 500),
                duration_days: +body.duration_days,
                issued_by: user.username,
                issued_by_role: user.role,
                created_at: now.toISOString(),
                expires_at: expires.toISOString(),
                justified_by: null,
                justified_at: null,
                justify_reason: null
            };
            data.items.push(item);
            saveWarningsFile(data);
            syncWarningToSheet(item.target_id, countActiveFor(data.items, item.target_id));
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true, item: { ...item, status: warnStatus(item) } }));
            return;
        }

        res.writeHead(405); res.end('Method Not Allowed'); return;
    }

    // POST /api/warnings/justify — снять (admin/asst — всем; curator — только мастерам)
    if (pathname === '/api/warnings-justify.php' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }
        const body = await readJsonBody(req);
        const id = +body.id;
        if (!id) { res.writeHead(400); res.end(JSON.stringify({ error: 'id required' })); return; }
        const data = loadWarnings();
        const idx = data.items.findIndex(w => w.id === id);
        if (idx < 0) { res.writeHead(404); res.end(JSON.stringify({ error: 'not found' })); return; }
        const myLevel = roleLevel(user.role);
        const canJustifyHere = myLevel >= 3 || (myLevel === 2 && data.items[idx].target_category === 'master');
        if (!canJustifyHere) {
            res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return;
        }
        if (data.items[idx].justified_at) {
            res.writeHead(400); res.end(JSON.stringify({ error: 'already justified' })); return;
        }
        data.items[idx].justified_by = user.username;
        data.items[idx].justified_at = new Date().toISOString();
        data.items[idx].justify_reason = (body.reason || '').slice(0, 300) || null;
        saveWarningsFile(data);
        syncWarningToSheet(data.items[idx].target_id, countActiveFor(data.items, data.items[idx].target_id));
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true, item: { ...data.items[idx], status: warnStatus(data.items[idx]) } }));
        return;
    }

    // POST /api/warnings/delete — удалить (только admin)
    if (pathname === '/api/warnings-delete.php' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (roleLevel(user.role) < 4) {
            res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return;
        }
        const body = await readJsonBody(req);
        const id = +body.id;
        if (!id) { res.writeHead(400); res.end(JSON.stringify({ error: 'id required' })); return; }
        const data = loadWarnings();
        const target = data.items.find(w => w.id === id);
        const before = data.items.length;
        data.items = data.items.filter(w => w.id !== id);
        if (data.items.length === before) { res.writeHead(404); res.end(JSON.stringify({ error: 'not found' })); return; }
        saveWarningsFile(data);
        if (target) syncWarningToSheet(target.target_id, countActiveFor(data.items, target.target_id));
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
        return;
    }

    // === Ивенты ===
    if (pathname === '/api/events') {
        const user = currentUser(req);
        if (!user) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }
        if (req.method === 'GET') {
            const data = loadEvents();
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ items: data.items }));
            return;
        }
        if (req.method === 'POST') {
            if (roleLevel(user.role) < 2) {
                res.writeHead(403, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'forbidden' }));
                return;
            }
            const body = await readJsonBody(req);
            const data = loadEvents();
            const now = new Date();
            const item = {
                id: data.next_id++,
                type: String(body.type || '').slice(0, 32),
                type_label: String(body.type_label || '').slice(0, 64),
                format: String(body.format || '').slice(0, 16),
                date: String(body.date || ''),
                time: String(body.time || ''),
                organizer: String(body.organizer || '').slice(0, 64),
                team1: Array.isArray(body.team1) ? body.team1.slice(0, 5).map(String) : [],
                team2: Array.isArray(body.team2) ? body.team2.slice(0, 5).map(String) : [],
                winner: body.winner ? +body.winner : null,
                first: String(body.first || ''),
                second: String(body.second || ''),
                third: String(body.third || ''),
                participants: Array.isArray(body.participants) ? body.participants.map(String) : [],
                prize_text: String(body.prize_text || '').slice(0, 500),
                notes: String(body.notes || '').slice(0, 1000),
                created_by: user.username,
                created_at: now.toISOString()
            };
            data.items.push(item);
            saveEventsFile(data);
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true, item }));
            return;
        }
        res.writeHead(405); res.end('Method Not Allowed'); return;
    }

    // POST /api/events/delete
    if (pathname === '/api/events/delete' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (roleLevel(user.role) < 3) {
            res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return;
        }
        const body = await readJsonBody(req);
        const id = +body.id;
        if (!id) { res.writeHead(400); res.end(JSON.stringify({ error: 'id required' })); return; }
        const data = loadEvents();
        const before = data.items.length;
        data.items = data.items.filter(w => w.id !== id);
        if (data.items.length === before) { res.writeHead(404); res.end(JSON.stringify({ error: 'not found' })); return; }
        saveEventsFile(data);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
        return;
    }

    // === Level up ===
    if (pathname === '/api/levelup') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (req.method === 'GET') {
            const data = loadLevelup();
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ items: data.items, max: LEVELUP_MAX }));
            return;
        }
        if (req.method === 'POST') {
            if (roleLevel(user.role) < 2) {
                res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return;
            }
            const body = await readJsonBody(req);
            if (!body.master_id) { res.writeHead(400); res.end(JSON.stringify({ error: 'master_id required' })); return; }
            const reason = String(body.reason || '').trim();
            if (!reason) { res.writeHead(400); res.end(JSON.stringify({ error: 'reason required' })); return; }
            const data = loadLevelup();
            // Лимит 10 баллов на мастера
            const currentCount = data.items.filter(x => x.master_id === String(body.master_id)).length;
            if (currentCount >= LEVELUP_MAX) {
                res.writeHead(400); res.end(JSON.stringify({ error: 'Достигнут максимум — ' + LEVELUP_MAX + ' баллов' })); return;
            }
            const item = {
                id: data.next_id++,
                master_id: String(body.master_id),
                master_nick: String(body.master_nick || ''),
                master_name: String(body.master_name || ''),
                reason: reason.slice(0, 500),
                given_by: user.username,
                given_at: new Date().toISOString()
            };
            data.items.push(item);
            saveLevelupFile(data);
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true, item }));
            return;
        }
        res.writeHead(405); res.end('Method Not Allowed'); return;
    }

    if (pathname === '/api/levelup/delete' && req.method === 'POST') {
        const user = currentUser(req);
        if (!user) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }
        if (roleLevel(user.role) < 3) {
            res.writeHead(403); res.end(JSON.stringify({ error: 'forbidden' })); return;
        }
        const body = await readJsonBody(req);
        const id = +body.id;
        if (!id) { res.writeHead(400); res.end(JSON.stringify({ error: 'id required' })); return; }
        const data = loadLevelup();
        const before = data.items.length;
        data.items = data.items.filter(w => w.id !== id);
        if (data.items.length === before) { res.writeHead(404); res.end(JSON.stringify({ error: 'not found' })); return; }
        saveLevelupFile(data);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
        return;
    }

    // === Discord OAuth ===
    if (pathname === '/auth/discord' && req.method === 'GET') {
        if (!DISCORD_CLIENT_ID) {
            res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLogin('Discord-вход не настроен (нет DISCORD_CLIENT_ID в .env).'));
            return;
        }
        const authUrl = 'https://discord.com/api/oauth2/authorize'
            + '?client_id=' + encodeURIComponent(DISCORD_CLIENT_ID)
            + '&redirect_uri=' + encodeURIComponent(DISCORD_REDIRECT_URI)
            + '&response_type=code&scope=identify';
        res.writeHead(302, { 'Location': authUrl });
        res.end();
        return;
    }

    if (pathname === '/auth/discord/callback' && req.method === 'GET') {
        const code = u.searchParams.get('code');
        if (!code) {
            res.writeHead(400, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLogin('Discord не вернул код авторизации.'));
            return;
        }
        try {
            const tokenRes = await fetch('https://discord.com/api/oauth2/token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    client_id:     DISCORD_CLIENT_ID,
                    client_secret: DISCORD_CLIENT_SECRET,
                    grant_type:    'authorization_code',
                    code,
                    redirect_uri:  DISCORD_REDIRECT_URI
                }).toString()
            });
            const tokenData = await tokenRes.json();
            if (!tokenData.access_token) {
                res.writeHead(401, { 'Content-Type': 'text/html; charset=utf-8' });
                res.end(renderLogin('Не удалось получить токен Discord: ' + (tokenData.error_description || tokenData.error || 'unknown')));
                return;
            }
            const userRes = await fetch('https://discord.com/api/users/@me', {
                headers: { 'Authorization': 'Bearer ' + tokenData.access_token }
            });
            const dUser = await userRes.json();
            const discordId = dUser && dUser.id;
            if (!discordId) {
                res.writeHead(401, { 'Content-Type': 'text/html; charset=utf-8' });
                res.end(renderLogin('Не удалось получить юзера Discord.'));
                return;
            }
            const staffMember = await findInHighStaff(discordId);
            if (!staffMember) {
                res.writeHead(403, { 'Content-Type': 'text/html; charset=utf-8' });
                res.end(renderLogin('Нет в группе состава'));
                return;
            }
            const sid = crypto.randomBytes(24).toString('hex');
            sessions.set(sid, {
                username: staffMember.username || dUser.global_name || dUser.username || ('user_' + discordId.slice(-4)),
                role: staffMember.role,
                discord_id: discordId
            });
            res.writeHead(302, {
                'Set-Cookie': `sid=${sid}; Path=/; HttpOnly; Max-Age=86400`,
                'Location': '/'
            });
            res.end();
        } catch (e) {
            console.error('OAuth error:', e);
            res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderLogin('Ошибка авторизации: ' + e.message));
        }
        return;
    }

    // GET /login или /login.php
    if (pathname === '/login' || pathname === '/login.php') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderLogin());
        return;
    }

    // /logout (GET и POST)
    if (pathname === '/logout' || pathname === '/logout.php') {
        const sid = parseCookies(req).sid;
        if (sid) sessions.delete(sid);
        res.writeHead(302, {
            'Set-Cookie': 'sid=; Path=/; Max-Age=0',
            'Location': '/login'
        });
        res.end();
        return;
    }

    // GET / или /index.php — нужен логин
    if (pathname === '/' || pathname === '/index.php' || pathname === '/index.html') {
        const user = currentUser(req);
        if (!user) {
            res.writeHead(302, { 'Location': '/login' });
            res.end();
            return;
        }
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(await renderIndex(user));
        return;
    }

    // Статика
    let filePath = path.join(ROOT, decodeURIComponent(pathname));
    if (!filePath.startsWith(ROOT)) {
        res.writeHead(403); res.end('Forbidden'); return;
    }
    // защита: не отдаём приватные файлы
    const blocked = ['.env', 'users.json', 'bot.js', 'package.json', 'package-lock.json',
                     'local-server.js', 'db.php', 'auth_helper.php', 'login.php', 'logout.php', 'index.php'];
    if (blocked.some(b => filePath.toLowerCase().endsWith(b))) {
        res.writeHead(403); res.end('Forbidden'); return;
    }
    fs.readFile(filePath, (err, data) => {
        if (err) { res.writeHead(404); res.end('Not found'); return; }
        const ext = path.extname(filePath).toLowerCase();
        res.writeHead(200, { 'Content-Type': TYPES[ext] || 'application/octet-stream' });
        res.end(data);
    });
});

server.listen(PORT, () => {
    console.log(`🌐 Сайт: http://localhost:${PORT}`);
    console.log(`📂 users.json: ${USERS_PATH}`);
    if (SHEETS_WEBHOOK_URL) {
        console.log(`📤 Sheets webhook: ON (${SHEETS_WEBHOOK_URL.slice(0, 60)}...)`);
    } else {
        console.log('📤 Sheets webhook: OFF (SHEETS_WEBHOOK_URL не задан)');
    }
    if (!fs.existsSync(USERS_PATH)) {
        console.log('⚠️  users.json пока нет. Вызови /доступ в Discord — бот создаст файл.');
    }
});
