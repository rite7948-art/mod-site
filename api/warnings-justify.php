<?php
// POST — снять выговор (admin/asst — всем; curator — только мастерам).
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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($body['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'id required']);
    exit;
}

$data = load_warnings();
$idx = null;
foreach ($data['items'] as $i => $w) {
    if ((int)$w['id'] === $id) { $idx = $i; break; }
}
if ($idx === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$myLevel = role_level_local($_SESSION['role'] ?? '');
$canJustifyHere = $myLevel >= 3 || ($myLevel === 2 && $data['items'][$idx]['target_category'] === 'master');
if (!$canJustifyHere) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
if (!empty($data['items'][$idx]['justified_at'])) {
    http_response_code(400);
    echo json_encode(['error' => 'already justified']);
    exit;
}

$data['items'][$idx]['justified_by'] = $_SESSION['username'] ?? '';
$data['items'][$idx]['justified_at'] = gmdate('Y-m-d\TH:i:s\Z');
$reason = mb_substr((string)($body['reason'] ?? ''), 0, 300);
$data['items'][$idx]['justify_reason'] = $reason !== '' ? $reason : null;

save_warnings($data);
sync_warning_to_sheet($data['items'][$idx]['target_id'], count_active_for($data['items'], $data['items'][$idx]['target_id']));

$item = $data['items'][$idx];
$item['status'] = warn_status($item);
echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
