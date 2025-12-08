<?php
// public/verify.php

ob_start();

// Включаем отображение ошибок, чтобы не видеть белый экран или 500, если что-то пойдет не так
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';


$error = '';
$success = '';

// Получаем email из ссылки (GET) или из формы (POST)
$email = $_GET['email'] ?? $_POST['email'] ?? '';

// Обработка формы подтверждения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || empty($code)) {
        $error = "Введите email и код подтверждения.";
    } else {
        try {
            // Ищем пользователя
            $stmt = $pdo->prepare("SELECT id, verify_code_hash, verify_expires, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "Пользователь с таким email не найден.";
            } elseif ($user['is_verified'] == 1) {
                $success = "Аккаунт уже подтвержден. <a href='login.php'>Войти</a>";
            } else {
                // Проверяем срок действия кода
                if (time() > $user['verify_expires']) {
                    $error = "Срок действия кода истек. Попробуйте запросить новый при входе.";
                } 
                // Проверяем сам код (он хранится как хеш)
                elseif (!password_verify($code, $user['verify_code_hash'])) {
                    $error = "Неверный код подтверждения.";
                } 
                else {
                    // Всё ок — активируем аккаунт
                    $updateStmt = $pdo->prepare("UPDATE users SET is_verified = 1, verify_code_hash = NULL, verify_expires = NULL WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    // Перенаправляем на логин или показываем успех
                    while (ob_get_level()) ob_end_clean();
                    header("Location: login.php?verified=1");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Подтверждение почты</title>
<link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
  <style>
      .alert-error { color: red; margin-bottom: 15px; }
      .alert-success { color: green; margin-bottom: 15px; }
      .form-group { margin-bottom: 15px; }
      .form-group input { width: 100%; padding: 8px; }
  </style>
</head>
<body>
  <?php render_header(); ?>
  <div class="container">
    <h1>Подтверждение регистрации</h1>
    
    <p>На почту <b><?= htmlspecialchars($email) ?></b> был отправлен код.</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success"><?= $success ?></div>
    <?php else: ?>

    <form method="POST" action="verify.php">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
      
      <div class="form-group">
        <label>Введите код из письма:</label>
        <input type="text" name="code" required placeholder="123456" autocomplete="off">
      </div>

      <button type="submit">Подтвердить</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
