<?php
// GET — список выговоров. POST — выдать новый (admin/asst — всем;
// curator — только мастерам; см. ту же логику в local-server.js).
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_warnings_shared.php';

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = load_warnings();
    $items = array_map(function ($w) {
        $w['status'] = warn_status($w);
        return $w;
    }, $data['items']);
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $required = ['target_id', 'target_nick', 'target_category', 'reason', 'duration_days'];
    foreach ($required as $k) {
        if (!isset($body[$k]) || $body[$k] === '') {
            http_response_code(400);
            echo json_encode(['error' => 'missing field: ' . $k]);
            exit;
        }
    }

    $myLevel = role_level_local($_SESSION['role'] ?? '');
    $canIssueHere = $myLevel >= 3 || ($myLevel === 2 && $body['target_category'] === 'master');
    if (!$canIssueHere) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $data = load_warnings();
    $now = time();
    $durationDays = (int)$body['duration_days'];
    $item = [
        'id' => $data['next_id']++,
        'target_id' => (string)$body['target_id'],
        'target_nick' => (string)$body['target_nick'],
        'target_name' => (string)($body['target_name'] ?? ''),
        'target_category' => (string)($body['target_category'] ?? 'master'),
        'reason' => mb_substr((string)$body['reason'], 0, 500),
        'duration_days' => $durationDays,
        'issued_by' => $_SESSION['username'] ?? '',
        'issued_by_role' => $_SESSION['role'] ?? '',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $now + $durationDays * 86400),
        'justified_by' => null,
        'justified_at' => null,
        'justify_reason' => null,
    ];
    $data['items'][] = $item;
    save_warnings($data);
    sync_warning_to_sheet($item['target_id'], count_active_for($data['items'], $item['target_id']));

    $item['status'] = warn_status($item);
    echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
