<?php
// PDO-подключение для сайта модераторов.
// Использует те же env-переменные, что и футика2 (Railway MYSQL plugin).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host   = getenv('MYSQLHOST')     ?: '127.0.0.1';
$port   = getenv('MYSQLPORT')     ?: '3306';
$user   = getenv('MYSQLUSER')     ?: 'root';
$pass   = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'futurama';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec("SET NAMES utf8mb4");

    // Таблица пользователей — общая с футика2 (если оба сайта в одной БД,
    // бот пишет сюда же через users.json). Создаём, если её ещё нет.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            discord_id VARCHAR(50) DEFAULT '',
            role VARCHAR(32) DEFAULT 'master',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    // Отчёты собесов — отдельная таблица для этого сайта (префикс `moder_site_`,
    // чтобы не конфликтовать с другими таблицами в общей БД).
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS moder_site_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('moder','master') NOT NULL,
            candidate_nick VARCHAR(128) NOT NULL,
            candidate_id VARCHAR(64) NOT NULL,
            reviewer VARCHAR(128) DEFAULT '',
            variant VARCHAR(16) DEFAULT '',
            score DECIMAL(5,1) NOT NULL,
            max_score INT NOT NULL,
            verdict ENUM('pass','fail') NOT NULL,
            ratings_json TEXT,
            created_by VARCHAR(64) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_created (type, created_at)
        )");
    } catch (Exception $e) {}

    // Сидим админа на первый запуск (только если таблица пустая).
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($cnt === 0) {
            $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
                ->execute(['admin', 'admin', 'admin']);
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    http_response_code(500);
    die('Ошибка подключения к БД: ' . htmlspecialchars($e->getMessage()));
}
