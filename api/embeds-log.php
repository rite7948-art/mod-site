<?php
// История последних отправленных эмбитов — для вкладки "Эмбиты" на сайте.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

function role_level_local_embeds($role) {
    $map = ['master' => 1, 'curator' => 2, 'chief' => 3, 'asst' => 4, 'admin' => 4];
    return $map[$role] ?? 0;
}
if (role_level_local_embeds($_SESSION['role'] ?? '') < 2) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$logPath = getenv('EMBEDS_LOG_JSON_PATH') ?: (__DIR__ . '/../embeds_log.json');
$log = json_decode(@file_get_contents($logPath) ?: '{}', true) ?: [];
$items = is_array($log['items'] ?? null) ? $log['items'] : [];

echo json_encode(['items' => array_reverse($items)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
