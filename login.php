<?php
session_start();

if (!empty($_SESSION['user_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = $_SESSION['discord_login_error'] ?? '';
unset($_SESSION['discord_login_error']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Futurama Moderator</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-locked">
    <div class="auth-screen show">
        <div class="auth-card">
            <div class="auth-logo">
                <i class="fas fa-shield-halved"></i>
                <div>
                    <div class="auth-brand">Futurama</div>
                    <div class="auth-brand-sub">Moderator</div>
                </div>
            </div>
            <h2 class="auth-title">Вход в панель</h2>

            <?php if ($error !== ''): ?>
            <div class="auth-error show"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <a href="auth_discord.php" class="auth-discord-btn">
                <i class="fab fa-discord"></i> Войти через Discord
            </a>
        </div>
    </div>
</body>
</html>
