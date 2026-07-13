<?php
// POST — удалить выговор без следа (только admin/asst, level >= 4).
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_warnings_shared.php';

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}
if (role_level_local($_SESSION['role'] ?? '') < 4) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($body['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'id required']);
    exit;
}

$data = load_warnings();
$target = null;
$newItems = [];
foreach ($data['items'] as $w) {
    if ((int)$w['id'] === $id) { $target = $w; continue; }
    $newItems[] = $w;
}
if ($target === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}
$data['items'] = $newItems;
save_warnings($data);
sync_warning_to_sheet($target['target_id'], count_active_for($data['items'], $target['target_id']));

echo json_encode(['ok' => true]);
