<?php
// Прокси к внутреннему Node-сервису (sync_service.js) — как и high-staff.php,
// в PHP-контейнере нет Node.js, поэтому сам селфбот-скрипт запускается там.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$serviceUrl = getenv('SYNC_SERVICE_URL') ?: '';
$token = getenv('INTERNAL_SYNC_TOKEN') ?: '';
if (!$serviceUrl || !$token) {
    http_response_code(500);
    echo json_encode(['error' => 'sync service не настроен (нет SYNC_SERVICE_URL / INTERNAL_SYNC_TOKEN)']);
    exit;
}

$ch = curl_init(rtrim($serviceUrl, '/') . '/voice-activity');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Internal-Token: ' . $token]);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => 'sync service недоступен: ' . $err]);
    exit;
}

http_response_code($status ?: 502);
echo $resp;
