<?php
// Отправка эмбита в один из двух официальных инфо-каналов от имени бота.
// Обычный REST-запрос к Discord API токеном бота — селфбот/Node-сервис
// тут не нужны. Каналы жёстко зашиты списком, клиент выбирает только ключ
// ('master'/'curator'), не сырой channel_id — чтобы нельзя было запостить
// в произвольный канал.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

function role_level_local($role) {
    $map = ['master' => 1, 'curator' => 2, 'chief' => 3, 'asst' => 4, 'admin' => 4];
    return $map[$role] ?? 0;
}
if (role_level_local($_SESSION['role'] ?? '') < 2) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$EMBED_CHANNELS = [
    'master'  => '1510992131446018139',
    'curator' => '1510992164392538163',
];

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$channelKey = (string)($body['channel'] ?? '');
$channelId = $EMBED_CHANNELS[$channelKey] ?? null;
if (!$channelId) {
    http_response_code(400);
    echo json_encode(['error' => 'bad channel']);
    exit;
}

$title = mb_substr(trim((string)($body['title'] ?? '')), 0, 256);
$description = mb_substr(trim((string)($body['description'] ?? '')), 0, 4096);
$image = trim((string)($body['image'] ?? ''));

if ($title === '' && $description === '') {
    http_response_code(400);
    echo json_encode(['error' => 'empty embed']);
    exit;
}

$color = 0xe5352b;
if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)($body['color'] ?? ''))) {
    $color = hexdec(substr($body['color'], 1));
}

$embed = ['color' => $color];
if ($title !== '') $embed['title'] = $title;
if ($description !== '') $embed['description'] = $description;
if ($image !== '' && preg_match('#^https?://#i', $image)) $embed['image'] = ['url' => $image];
$embed['footer'] = ['text' => 'Опубликовал ' . ($_SESSION['username'] ?? '?')];
$embed['timestamp'] = gmdate('c');

$token = getenv('DISCORD_TOKEN') ?: '';
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'DISCORD_TOKEN не настроен']);
    exit;
}

$ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bot ' . $token,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['embeds' => [$embed]], JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Discord HTTP ' . $status . ': ' . substr((string)$resp, 0, 300)]);
    exit;
}

$logPath = getenv('EMBEDS_LOG_JSON_PATH') ?: (__DIR__ . '/../embeds_log.json');
$log = json_decode(@file_get_contents($logPath) ?: '{}', true) ?: [];
$log['next_id'] = $log['next_id'] ?? 1;
$log['items'] = is_array($log['items'] ?? null) ? $log['items'] : [];
$log['items'][] = [
    'id' => $log['next_id']++,
    'channel' => $channelKey,
    'title' => $title,
    'description' => $description,
    'image' => $image,
    'color' => (string)($body['color'] ?? '#e5352b'),
    'sent_by' => $_SESSION['username'] ?? '',
    'created_at' => gmdate('c'),
];
if (count($log['items']) > 30) $log['items'] = array_slice($log['items'], -30);
file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode(['ok' => true]);
