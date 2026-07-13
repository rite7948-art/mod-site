<?php
// Общие хелперы для api/warnings.php, api/warnings-justify.php,
// api/warnings-delete.php — три места делают одно и то же с одним файлом
// данных, вынесено, чтобы не тройить логику статуса/синка в таблицу.

function role_level_local($role) {
    $map = ['master' => 1, 'curator' => 2, 'chief' => 3, 'asst' => 4, 'admin' => 4];
    return $map[$role] ?? 0;
}

function warnings_file_path() {
    return getenv('WARNINGS_JSON_PATH') ?: (__DIR__ . '/../warnings.json');
}

function load_warnings() {
    $d = json_decode(@file_get_contents(warnings_file_path()) ?: '{}', true) ?: [];
    return [
        'next_id' => $d['next_id'] ?? 1,
        'items' => is_array($d['items'] ?? null) ? $d['items'] : [],
    ];
}

function save_warnings($data) {
    file_put_contents(warnings_file_path(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Гарантирует валидный UTF-8 в свободном пользовательском тексте (причина
// выговора и т.п.) — иначе json_encode() тихо вернёт false и клиент получит
// пустой 200-ответ, на котором fetch(...).json() падает без единой строки
// в логах (снаружи выглядит как «кнопка выдать ничего не делает»).
function clean_utf8($s) {
    return mb_convert_encoding((string)$s, 'UTF-8', 'UTF-8');
}

// Всегда отдаёт валидный JSON — если json_encode всё же не смог (данные
// не удалось привести к UTF-8), возвращает читаемую 500-ошибку вместо
// пустого тела ответа.
function json_response($data, $code = null) {
    if ($code !== null) http_response_code($code);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['error' => 'json_encode failed: ' . json_last_error_msg()]);
        exit;
    }
    echo $json;
}

function warn_is_expired($w) {
    return !empty($w['expires_at']) && strtotime($w['expires_at']) <= time();
}

function warn_status($w) {
    if (!empty($w['justified_at'])) return 'justified';
    if (warn_is_expired($w)) return 'expired';
    return 'active';
}

function count_active_for($items, $discordId) {
    $c = 0;
    foreach ($items as $w) {
        if ($w['target_id'] === $discordId && warn_status($w) === 'active') $c++;
    }
    return $c;
}

// Пишет текущий счёт выговоров обратно в гугл-таблицу через Apps Script webhook
// (см. SHEETS_SYNC_SETUP.md) — тот же механизм, что и в local-server.js.
function sync_warning_to_sheet($discordId, $count) {
    $url = getenv('SHEETS_WEBHOOK_URL') ?: '';
    if (!$url) return;
    $token = getenv('SHEETS_WEBHOOK_TOKEN') ?: '';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'token' => $token,
        'action' => 'update_warning',
        'discord_id' => (string)$discordId,
        'count' => max(0, min(3, (int)$count)),
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}
