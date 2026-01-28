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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
  <?php render_header(); ?>
  <div class="w-11/12 max-w-xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Подтверждение регистрации</h1>
    
    <p class="mt-2 text-slate-700">На почту <b><?= htmlspecialchars($email) ?></b> был отправлен код.</p>

    <?php if ($error): ?>
        <div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mt-4 mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800"><?= $success ?></div>
    <?php else: ?>

    <form method="POST" action="verify.php">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
      
      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">Введите код из письма:</label>
        <input type="text" name="code" required placeholder="123456" autocomplete="off" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
      </div>

      <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Подтвердить</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
