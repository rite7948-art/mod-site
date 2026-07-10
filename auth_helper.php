<?php
// Хелперы авторизации и проверки ролей.

require_once __DIR__ . '/db.php';

function require_login() {
    if (empty($_SESSION['user_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user() {
    return [
        'username'   => $_SESSION['username']   ?? '',
        'role'       => $_SESSION['role']       ?? 'master',
        'discord_id' => $_SESSION['discord_id'] ?? '',
    ];
}

function role_level($role) {
    $map = ['master' => 1, 'curator' => 2, 'chief' => 3, 'asst' => 4, 'admin' => 4];
    return $map[$role] ?? 0;
}

function has_role($min_role) {
    return role_level($_SESSION['role'] ?? '') >= role_level($min_role);
}

function role_display_name($role) {
    $names = [
        'master'  => 'Мастер',
        'curator' => 'Куратор',
        'chief'   => 'Главный куратор',
        'asst'    => 'Ассистент админа',
        'admin'   => 'Администратор',
    ];
    return $names[$role] ?? 'Без роли';
}

function require_role($min_role) {
    require_login();
    if (!has_role($min_role)) {
        http_response_code(403);
        die('У вас нет доступа.');
    }
}
