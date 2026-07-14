<?php
// Изменение уже отправленного сообщения (1-10 эмбитов) в одном из двух
// инфо-каналов.
// GET  — подтягивает текущее содержимое сообщения (по ссылке пользователь
//        мог вставить чужую правку, которую сайт ещё не видел).
// POST — применяет правки через PATCH к тому же сообщению.
// channel_id всегда сверяется со списком известных инфо-каналов — нельзя
// протащить произвольный канал/сообщение.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_embeds_shared.php';

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

function role_level_local_edit($role) {
    $map = ['master' => 1, 'curator' => 2, 'chief' => 3, 'asst' => 4, 'admin' => 4];
    return $map[$role] ?? 0;
}
if (role_level_local_edit($_SESSION['role'] ?? '') < 2) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$EMBED_CHANNELS = embed_channels();
$channelKeyById = array_flip($EMBED_CHANNELS);

$token = getenv('DISCORD_TOKEN') ?: '';
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'DISCORD_TOKEN не настроен']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $channelId = trim((string)($_GET['channel_id'] ?? ''));
    $messageId = trim((string)($_GET['message_id'] ?? ''));
    if (!isset($channelKeyById[$channelId]) || !preg_match('/^\d{15,22}$/', $messageId)) {
        http_response_code(400);
        echo json_encode(['error' => 'это не ссылка на инфо-канал']);
        exit;
    }

    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages/{$messageId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bot ' . $token]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $status >= 300) {
        http_response_code($status === 404 ? 404 : 502);
        echo json_encode(['error' => $status === 404 ? 'сообщение не найдено' : ('Discord HTTP ' . $status)]);
        exit;
    }

    $msg = json_decode((string)$resp, true) ?: [];
    $rawEmbeds = is_array($msg['embeds'] ?? null) ? $msg['embeds'] : [];
    $embeds = array_map(function ($e) {
        return [
            'title' => $e['title'] ?? '',
            'description' => $e['description'] ?? '',
            'image' => $e['image']['url'] ?? '',
            'color' => isset($e['color']) ? ('#' . str_pad(dechex($e['color']), 6, '0', STR_PAD_LEFT)) : '#e5352b',
        ];
    }, $rawEmbeds);
    if (count($embeds) === 0) $embeds = [['title' => '', 'description' => '', 'image' => '', 'color' => '#e5352b']];

    echo json_encode([
        'channel' => $channelKeyById[$channelId],
        'channel_id' => $channelId,
        'message_id' => $messageId,
        'embeds' => $embeds,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $channelId = trim((string)($body['channel_id'] ?? ''));
    $messageId = trim((string)($body['message_id'] ?? ''));
    if (!isset($channelKeyById[$channelId]) || !preg_match('/^\d{15,22}$/', $messageId)) {
        http_response_code(400);
        echo json_encode(['error' => 'это не ссылка на инфо-канал']);
        exit;
    }

    $username = $_SESSION['username'] ?? '?';
    [$discordEmbeds, $cleaned, $error] = build_discord_embeds($body['embeds'] ?? [], 'Отредактировал', $username);
    if ($error) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
        exit;
    }

    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages/{$messageId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
        http_response_code($status === 404 ? 404 : 502);
        echo json_encode(['error' => $status === 404 ? 'сообщение не найдено' : ('Discord HTTP ' . $status . ': ' . substr((string)$resp, 0, 300))]);
        exit;
    }

    // Если это сообщение уже есть в нашей истории — обновим запись, чтобы
    // список "Последние отправленные" показывал актуальный текст.
    $logPath = getenv('EMBEDS_LOG_JSON_PATH') ?: (__DIR__ . '/../embeds_log.json');
    $log = json_decode(@file_get_contents($logPath) ?: '{}', true) ?: [];
    $log['items'] = is_array($log['items'] ?? null) ? $log['items'] : [];
    foreach ($log['items'] as &$item) {
        if (($item['message_id'] ?? null) === $messageId) {
            $item['embeds'] = $cleaned;
            $item['edited_by'] = $username;
            $item['edited_at'] = gmdate('c');
        }
    }
    unset($item);
    file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
