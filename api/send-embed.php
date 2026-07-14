<?php
// Отправка одного или нескольких эмбитов (до 10, как позволяет Discord) в
// один из двух официальных инфо-каналов от имени бота. Обычный REST-запрос
// токеном бота — селфбот/Node-сервис тут не нужны. Каналы жёстко зашиты
// списком, клиент выбирает только ключ ('master'/'curator'), не сырой
// channel_id — чтобы нельзя было запостить в произвольный канал.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_embeds_shared.php';

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

$EMBED_CHANNELS = embed_channels();

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$channelKey = (string)($body['channel'] ?? '');
$channelId = $EMBED_CHANNELS[$channelKey] ?? null;
if (!$channelId) {
    http_response_code(400);
    echo json_encode(['error' => 'bad channel']);
    exit;
}

$username = $_SESSION['username'] ?? '?';
[$discordEmbeds, $cleaned, $error] = build_discord_embeds($body['embeds'] ?? [], 'Опубликовал', $username);
if ($error) {
    http_response_code(400);
    echo json_encode(['error' => $error]);
    exit;
}

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
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['embeds' => $discordEmbeds], JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Discord HTTP ' . $status . ': ' . substr((string)$resp, 0, 300)]);
    exit;
}

$sentMessage = json_decode((string)$resp, true) ?: [];

$logPath = getenv('EMBEDS_LOG_JSON_PATH') ?: (__DIR__ . '/../embeds_log.json');
$log = json_decode(@file_get_contents($logPath) ?: '{}', true) ?: [];
$log['next_id'] = $log['next_id'] ?? 1;
$log['items'] = is_array($log['items'] ?? null) ? $log['items'] : [];
$log['items'][] = [
    'id' => $log['next_id']++,
    'channel' => $channelKey,
    'channel_id' => $channelId,
    'message_id' => $sentMessage['id'] ?? null,
    'embeds' => $cleaned,
    'sent_by' => $username,
    'created_at' => gmdate('c'),
];
if (count($log['items']) > 30) $log['items'] = array_slice($log['items'], -30);
file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode(['ok' => true, 'message_id' => $sentMessage['id'] ?? null, 'channel_id' => $channelId]);
