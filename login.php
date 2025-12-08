<?php
// public/login.php

// Буферизация вывода для безопасной отправки header()
ob_start();

session_start();
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/src/db.php';

$error = '';
$message = '';

if (isset($_GET['verified'])) {
    $message = "Email подтвержден! Теперь вы можете войти.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Проверка подтверждения почты
            if ($user['is_verified'] == 0) {
                $error = "Пожалуйста, подтвердите ваш email перед входом.";
            } else {
                // ВСЁ ОК: Записываем данные в сессию
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];           // admin или resident
                $_SESSION['full_name'] = $user['full_name']; // Для приветствия
                $_SESSION['apartment'] = $user['apartment']; // Чтобы знать, чья заявка
                
                // Очищаем буфер перед редиректом
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header("Location: index.php");
                exit;
            }
        } else {
            $error = "Неверный email или пароль.";
        }
    } catch (PDOException $e) {
        $error = "Ошибка сервера.";
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в ТСЖ</title>
<link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
    <style>
        .msg { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php render_header(); ?>
<div class="container">
    <h1>Вход</h1>
    
    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <label>Email <input type="email" name="email" required></label><br><br>
        <label>Пароль <input type="password" name="password" required></label><br><br>
        <button type="submit">Войти</button>
    </form>
    <p>Нет аккаунта? <a href="register.php">Регистрация</a></p>
</div>
</body>
</html>
