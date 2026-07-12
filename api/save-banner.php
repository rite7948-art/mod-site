<?php
// Сохраняет баннер профиля текущего юзера в общий profiles.json — он же
// подмешивается в /api/high-staff.php, поэтому баннер виден всем, кто
// открывает "Состав вышки", а не только владельцу в его браузере.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$discordId = (string)($_SESSION['discord_id'] ?? '');
if ($discordId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'no discord_id']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$banner = trim((string)($body['banner'] ?? ''));

if (strlen($banner) > 2 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'banner too large']);
    exit;
}
if ($banner !== '' && !preg_match('#^(data:image/|https?://)#i', $banner)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad banner format']);
    exit;
}

$path = __DIR__ . '/../profiles.json';
$profiles = json_decode(@file_get_contents($path) ?: '{}', true) ?: [];
if ($banner === '') {
    unset($profiles[$discordId]);
} else {
    $profiles[$discordId] = ['banner' => $banner, 'updated_at' => date('c')];
}
file_put_contents($path, json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode(['ok' => true]);
