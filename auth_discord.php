<?php
// Вход через Discord OAuth. Единственный способ входа в панель.
// Роль/доступ не берутся из БД — сверяются напрямую с секцией "High staff"
// той же гугл-таблицы, где ведётся состав вышки (Administrator /
// Administrative Assistant / Curator / Master). Логика 1:1 с local-server.js.
session_start();

function auth_discord_fail($msg) {
    $_SESSION['discord_login_error'] = $msg;
    header('Location: login.php');
    exit;
}

$clientId     = getenv('DISCORD_CLIENT_ID') ?: '';
$clientSecret = getenv('DISCORD_CLIENT_SECRET') ?: '';

if (!$clientId || !$clientSecret) {
    auth_discord_fail('Discord-вход не настроен (нет DISCORD_CLIENT_ID / DISCORD_CLIENT_SECRET в .env).');
}

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$redirectUri = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/auth_discord.php';

// === Шаг 1: нет кода — отправляем на авторизацию Discord ===
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['discord_oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'identify',
        'state'         => $state,
    ]);
    header('Location: https://discord.com/api/oauth2/authorize?' . $params);
    exit;
}

// === Шаг 2: вернулись с кодом — проверяем state (CSRF) ===
$state = $_GET['state'] ?? '';
if (!$state || !isset($_SESSION['discord_oauth_state']) || !hash_equals($_SESSION['discord_oauth_state'], $state)) {
    auth_discord_fail('Сбой проверки безопасности (state). Попробуй ещё раз.');
}
unset($_SESSION['discord_oauth_state']);

// === Шаг 3: код -> access_token ===
$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => $redirectUri,
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$tokenResp = curl_exec($ch);
$tokenCurlErr = curl_error($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$tokenData = json_decode((string)$tokenResp, true);
if (!isset($tokenData['access_token'])) {
    error_log('[auth_discord] token exchange failed. curl_error=' . $tokenCurlErr . ' http_code=' . $tokenHttpCode . ' redirect_uri=' . $redirectUri . ' body=' . substr((string)$tokenResp, 0, 500));
    auth_discord_fail('Не удалось получить токен Discord: ' . htmlspecialchars($tokenData['error_description'] ?? $tokenData['error'] ?? 'unknown'));
}

// === Шаг 4: данные пользователя Discord ===
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$userResp = curl_exec($ch);
curl_close($ch);
$dUser = json_decode((string)$userResp, true);
$discordId = $dUser['id'] ?? '';
if (!$discordId) {
    auth_discord_fail('Не удалось получить профиль Discord.');
}

// === Шаг 5: сверяем с секцией High staff в гугл-таблице ===
$staff = find_in_high_staff((string)$discordId);
if (!$staff) {
    auth_discord_fail('Нет в группе состава');
}

// === Шаг 6: логиним ===
$_SESSION['user_logged_in'] = true;
$_SESSION['username']       = ($staff['nick'] ?? '') !== '' ? $staff['nick'] : ($dUser['global_name'] ?? $dUser['username'] ?? ('user_' . substr((string)$discordId, -4)));
$_SESSION['role']           = $staff['role'];
$_SESSION['discord_id']     = (string)$discordId;

header('Location: index.php');
exit;

// ================= Helpers =================

// ID из этого списка всегда логинятся как admin, даже если их нет в таблице
// (или таблица временно недоступна) — на случай пробелов/лагов в High staff.
function super_admin_ids() {
    return array_filter(array_map('trim', explode(',', getenv('SUPER_ADMIN_IDS') ?: '')));
}

function find_in_high_staff($discordId) {
    $isSuperAdmin = in_array($discordId, super_admin_ids(), true);

    $csv = fetch_high_staff_csv();
    if ($csv === null) {
        return $isSuperAdmin ? ['nick' => null, 'role' => 'admin'] : null;
    }

    $headers = [
        'administrator'            => 'admin',
        'administrative assistant' => 'asst',
        'curator'                  => 'curator',
        'master'                   => 'master',
    ];
    $modHeaders = ['moderators', 'список модераторов'];

    $currentRole = null;
    foreach (parse_csv_text($csv) as $r) {
        $cellTexts = array_map(function ($c) {
            return mb_strtolower(normalize_homoglyphs(trim((string)$c)));
        }, $r);

        if (array_intersect($cellTexts, $modHeaders)) break; // дальше модеры, не вышка

        $headerHit = null;
        foreach ($cellTexts as $t) {
            if (isset($headers[$t])) { $headerHit = $t; break; }
        }
        if ($headerHit !== null) { $currentRole = $headers[$headerHit]; continue; }
        if ($currentRole === null) continue;

        $id = trim((string)($r[2] ?? ''));
        if (!preg_match('/^\d{15,22}$/', $id)) continue;
        if ($id === $discordId) {
            return ['nick' => trim((string)($r[3] ?? '')), 'role' => $isSuperAdmin ? 'admin' : $currentRole];
        }
    }
    return $isSuperAdmin ? ['nick' => null, 'role' => 'admin'] : null;
}

function fetch_high_staff_csv() {
    $url = 'https://docs.google.com/spreadsheets/d/15B4JWCgDLFZkzIqFZoQq9HMu0zgznDhKlLant1vddgU/export?format=csv&gid=87425732';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $text = curl_exec($ch);
    curl_close($ch);
    if ($text === false) return null;
    if (stripos($text, '<!doctype') === 0 || stripos($text, '<html') === 0) return null;
    return $text;
}

function parse_csv_text($text) {
    $rows = [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $text);
    rewind($fh);
    while (($row = fgetcsv($fh)) !== false) {
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

// В таблице заголовок "Аdministrative Аssistant" набран с кириллическими «А»
// вместо латинских — визуально не отличить, строкой не совпадает. Нормализуем
// похожие кириллические буквы в латиницу перед сравнением (та же логика, что
// и в local-server.js).
function normalize_homoglyphs($s) {
    static $map = [
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H',
        'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
        'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'х' => 'x',
    ];
    return strtr($s, $map);
}
