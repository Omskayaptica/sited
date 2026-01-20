<?php
// public/reset-password.php

session_start();
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/src/db.php';

$error = '';
$success = '';
$show_form = false;
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Проверяем токен
if (!empty($token) && !empty($email)) {
    // Ищем токен в БД
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id, u.email, u.full_name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE u.email = ? AND pr.expires_at > datetime('now') AND pr.used = 0
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$email]);
    $reset_requests = $stmt->fetchAll();
    
    $valid_token = false;
    foreach ($reset_requests as $request) {
        if (password_verify($token, $request['token_hash'])) {
            $valid_token = true;
            $reset_id = $request['id'];
            $user_id = $request['user_id'];
            $user_full_name = $request['full_name'];
            break;
        }
    }
    
    if (!$valid_token) {
        $error = "Ссылка для сброса пароля недействительна или устарела.";
    } else {
        $show_form = true;
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_id'] = $reset_id;
        $_SESSION['reset_user_id'] = $user_id;
        $_SESSION['reset_full_name'] = $user_full_name;
    }
}

// Обработка сброса пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем, что форма должна быть показана
    if (!$show_form) {
        $error = "Недействительная сессия. Пожалуйста, начните процесс восстановления заново.";
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $session_token = $_SESSION['reset_token'] ?? '';
        $session_email = $_SESSION['reset_email'] ?? '';
        
        // Проверка введенных данных
        if (strlen($password) < 8) {
            $error = "Пароль должен быть не менее 8 символов.";
        } elseif ($password !== $password_confirm) {
            $error = "Пароли не совпадают.";
        } elseif ($session_token !== $token || $session_email !== $email) {
            $error = "Ошибка безопасности. Обновите страницу.";
        }
        
        if (!$error) {
            try {
                $pdo->beginTransaction();
                
                // Обновляем пароль
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $current_time = date('Y-m-d H:i:s');
                
                // Запрос с проверкой существования колонки
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, last_password_reset = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$hash, $current_time, $_SESSION['reset_user_id']]);
                
                // Помечаем токен как использованный
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['reset_id']]);
                
                // Отправляем уведомление об изменении пароля
                require_once '/var/www/mysite/src/mail.php';
                sendPasswordChangedNotification($email, $_SESSION['reset_full_name'] ?? 'Пользователь');
                
                $pdo->commit();
                
                // Очищаем сессию
                unset(
                    $_SESSION['reset_token'], 
                    $_SESSION['reset_email'], 
                    $_SESSION['reset_id'],
                    $_SESSION['reset_user_id'],
                    $_SESSION['reset_full_name']
                );
                
                $success = "Пароль успешно изменен! Теперь вы можете войти.";
                $show_form = false;
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Ошибка при смене пароля: " . $e->getMessage();
                
                // Если ошибка из-за отсутствия колонки, предлагаем альтернативный запрос
                if (strpos($e->getMessage(), 'no such column: last_password_reset') !== false) {
                    // Попробуем без колонки last_password_reset
                    try {
                        $pdo->beginTransaction();
                        
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hash, $_SESSION['reset_user_id']]);
                        
                        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                        $stmt->execute([$_SESSION['reset_id']]);
                        
                        require_once '/var/www/mysite/src/mail.php';
                        sendPasswordChangedNotification($email, $_SESSION['reset_full_name'] ?? 'Пользователь');
                        
                        $pdo->commit();
                        
                        unset(
                            $_SESSION['reset_token'], 
                            $_SESSION['reset_email'], 
                            $_SESSION['reset_id'],
                            $_SESSION['reset_user_id'],
                            $_SESSION['reset_full_name']
                        );
                        
                        $success = "Пароль успешно изменен! Теперь вы можете войти.";
                        $show_form = false;
                        $error = ''; // Очищаем ошибку
                        
                    } catch (Exception $e2) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = "Ошибка при смене пароля: " . $e2->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля - ТСЖ Омская причал</title>
    <link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
    <style>
        .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 4px; margin: 15px 0; }
        .success { color: green; background: #e6ffe6; padding: 10px; border-radius: 4px; margin: 15px 0; }
        form label { display: block; margin-bottom: 15px; }
        form label input { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; }
        button[type="submit"] { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button[type="submit"]:hover { background: #218838; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <?php render_header(); ?>
    <div class="container">
        <h1>Сброс пароля</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <p><a href="login.php" style="display: inline-block; margin-top: 15px;">Перейти к входу</a></p>
        <?php endif; ?>
        
        <?php if ($show_form): ?>
            <form method="post">
                <label>
                    Новый пароль (минимум 8 символов)
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
                
                <label>
                    Подтвердите новый пароль
                    <input type="password" name="password_confirm" required autocomplete="new-password">
                </label>
                
                <button type="submit">Установить новый пароль</button>
            </form>
        <?php endif; ?>
        
        <?php if (!$show_form && !$success && !$error && empty($token)): ?>
            <div class="error">
                Неверная или устаревшая ссылка для сброса пароля.
                <p><a href="forgot-password.php">Запросить новую ссылку</a></p>
            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>
