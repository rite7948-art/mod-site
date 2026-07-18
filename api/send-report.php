<?php
// Пересылает отчёт о собеседовании (модер/мастер) в Telegram-группу через
// бота. Раньше отчёты жили только в localStorage браузера того, кто их
// отправил, и были не видны остальным — теперь о каждом узнаёт вся вышка.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$LABELS = ['moder' => 'Собес на модера', 'master' => 'Собес на мастера'];

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$type = (string)($body['type'] ?? '');
if (!isset($LABELS[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'bad type']);
    exit;
}

$nick = trim((string)($body['nick'] ?? ''));
$id = trim((string)($body['id'] ?? ''));
$reviewer = trim((string)($body['reviewer'] ?? '')) ?: ($_SESSION['username'] ?? '?');
$score = $body['score'] ?? '';
$maxScore = $body['maxScore'] ?? '';
$passed = !empty($body['passed']);
$variant = trim((string)($body['variant'] ?? ''));
$ratings = is_array($body['ratings'] ?? null) ? $body['ratings'] : [];

if ($nick === '' || $id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'nick/id required']);
    exit;
}

// Сдал «Собес на модера» — сразу добавляем строку в гугл-таблицу модеров
// через тот же Apps Script webhook, что синкает выговоры (см.
// SHEETS_SYNC_SETUP.md). Не блокирует остальной ответ — таблица не
// настроена или недоступна, отчёт всё равно уходит в Telegram как раньше.
if ($type === 'moder' && $passed) {
    $sheetsUrl = getenv('SHEETS_WEBHOOK_URL') ?: '';
    if ($sheetsUrl) {
        $ch2 = curl_init($sheetsUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
            'token' => getenv('SHEETS_WEBHOOK_TOKEN') ?: '',
            'action' => 'add_moderator',
            'date' => date('d.m.Y'),
            'discord_id' => $id,
            'nick' => $nick,
            'reviewer' => $reviewer,
        ], JSON_UNESCAPED_UNICODE));
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        curl_exec($ch2);
        curl_close($ch2);
    }
}

$token = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_REPORTS_CHAT_ID') ?: '';
$threadId = getenv('TELEGRAM_REPORTS_THREAD_ID') ?: '';
if (!$token || !$chatId) {
    http_response_code(500);
    echo json_encode(['error' => 'Telegram-бот не настроен (нет TELEGRAM_BOT_TOKEN / TELEGRAM_REPORTS_CHAT_ID)']);
    exit;
}

function clean_utf8_report($s) {
    return mb_convert_encoding((string)$s, 'UTF-8', 'UTF-8');
}

$lines = [
    $LABELS[$type],
    'Проверяющий: ' . clean_utf8_report($reviewer),
    'Кандидат: ' . clean_utf8_report($nick) . ' (ID: ' . $id . ')',
    'Результат: ' . $score . ($maxScore !== '' ? ' / ' . $maxScore : '') . ' — ' . ($passed ? 'Сдал' : 'Не сдал'),
];
if ($variant !== '') $lines[] = 'Вариант: ' . clean_utf8_report($variant);

if (count($ratings) > 0) {
    $keys = array_map('intval', array_keys($ratings));
    sort($keys);
    $lines[] = '';
    foreach ($keys as $k) {
        $lines[] = ($k + 1) . '. ' . clean_utf8_report($ratings[$k] ?? '');
    }
}

$text = implode("\n", $lines);

$payload = ['chat_id' => $chatId, 'text' => $text];
if ($threadId !== '') $payload['message_thread_id'] = (int)$threadId;

$ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Telegram HTTP ' . $status . ': ' . substr((string)$resp, 0, 300)]);
    exit;
}

echo json_encode(['ok' => true]);
