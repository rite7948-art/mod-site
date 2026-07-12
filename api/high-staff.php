<?php
// Прокси к внутреннему Node-сервису (sync_service.js) — см. sync-moderators.php
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

$ch = curl_init(rtrim($serviceUrl, '/') . '/high-staff');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Internal-Token: ' . $token]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => 'sync service недоступен: ' . $err]);
    exit;
}

// Подмешиваем баннеры профилей (profiles.json живёт тут же, в PHP-контейнере —
// Node-сервис о них не знает и знать не должен).
$data = json_decode($resp, true);
if (is_array($data) && isset($data['roster']) && is_array($data['roster'])) {
    $profiles = json_decode(@file_get_contents(__DIR__ . '/../profiles.json') ?: '{}', true) ?: [];
    foreach ($data['roster'] as &$members) {
        if (!is_array($members)) continue;
        foreach ($members as &$m) {
            $m['banner'] = $profiles[$m['id']]['banner'] ?? null;
        }
        unset($m);
    }
    unset($members);
    http_response_code($status ?: 502);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code($status ?: 502);
echo $resp;
