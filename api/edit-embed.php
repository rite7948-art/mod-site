<?php
// Изменение уже отправленного эмбита в одном из двух инфо-каналов.
// GET  — подтягивает текущее содержимое сообщения (по ссылке пользователь
//        мог вставить чужую правку, которую сайт ещё не видел).
// POST — применяет правки через PATCH к тому же сообщению.
// channel_id всегда сверяется со списком известных инфо-каналов — нельзя
// протащить произвольный канал/сообщение.
session_start();
header('Content-Type: application/json; charset=utf-8');

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

$EMBED_CHANNELS = [
    'master'  => '1510992131446018139',
    'curator' => '1510992164392538163',
];
$channelKeyById = array_flip($EMBED_CHANNELS);

$token = getenv('DISCORD_TOKEN') ?: '';
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'DISCORD_TOKEN не настроен']);
    exit;
}

function clean_utf8_edit($s) {
    return mb_convert_encoding((string)$s, 'UTF-8', 'UTF-8');
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
    $embed = ($msg['embeds'][0] ?? null);
    echo json_encode([
        'channel' => $channelKeyById[$channelId],
        'channel_id' => $channelId,
        'message_id' => $messageId,
        'title' => $embed['title'] ?? '',
        'description' => $embed['description'] ?? '',
        'image' => $embed['image']['url'] ?? '',
        'color' => $embed && isset($embed['color']) ? ('#' . str_pad(dechex($embed['color']), 6, '0', STR_PAD_LEFT)) : '#e5352b',
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

    $title = mb_substr(clean_utf8_edit(trim((string)($body['title'] ?? ''))), 0, 256);
    $description = mb_substr(clean_utf8_edit(trim((string)($body['description'] ?? ''))), 0, 4096);
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
    $embed['footer'] = ['text' => 'Отредактировал ' . ($_SESSION['username'] ?? '?')];
    $embed['timestamp'] = gmdate('c');

    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages/{$messageId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
            $item['title'] = $title;
            $item['description'] = $description;
            $item['image'] = $image;
            $item['color'] = (string)($body['color'] ?? '#e5352b');
            $item['edited_by'] = $_SESSION['username'] ?? '';
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
