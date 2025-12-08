<?php
// public/register.php

// Буферизация вывода (очень важна для header() после любого вывода)
ob_start();

// Настройки сессии (безопасность)
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'omkayaprica.shop', // Замените, если домен изменится
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// Подключаем необходимые файлы БЕЗ вывода HTML
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/inc/init.php'; // Здесь должен быть CSRF токен
require_once '/var/www/mysite/src/db.php';   // Подключение к БД ($pdo)
require_once '/var/www/mysite/src/mail.php'; // Функция sendVerificationCode

$error = '';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Получаем и чистим данные
    $csrf_post = $_POST['csrf'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    // Новые поля для ТСЖ
    $fullName = trim($_POST['full_name'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // 2. Валидация
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf_post)) {
        die("Ошибка безопасности (CSRF). Обновите страницу.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный email.";
    } elseif (strlen($password) < 8) {
        $error = "Пароль должен быть не менее 8 символов.";
    } elseif (empty($fullName) || empty($apartment)) {
        $error = "Пожалуйста, заполните ФИО и номер квартиры.";
    }

    // Если ошибок нет — пробуем регистрировать
    if (!$error) {
        try {
            // Проверяем, есть ли пользователь
            $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            // Генерируем данные для верификации
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $code = random_int(100000, 999999);
            $codeHash = password_hash((string)$code, PASSWORD_DEFAULT);
            $expires = time() + 15 * 60; // 15 минут

            // Проверяем может ли БД писать
            if (!$pdo->beginTransaction()) {
                throw new Exception("Не удалось начать транзакцию. БД может быть readonly.");
            }

            if ($exists) {
                // Если пользователь уже подтвержден — не даем регистрироваться повторно
                if ((int)$exists['is_verified'] === 1) {
                    $pdo->rollBack();
                    $error = "Пользователь с таким email уже зарегистрирован.";
                } else {
                    // Если пользователь есть, но НЕ подтвержден — обновляем данные
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password = ?, full_name = ?, apartment = ?, phone = ?, 
                            verify_code_hash = ?, verify_expires = ?, is_verified = 0, verify_attempts = 0 
                        WHERE id = ?
                    ");
                    $stmt->execute([$hash, $fullName, $apartment, $phone, $codeHash, $expires, $exists['id']]);
                }
            } else {
                // Новый пользователь
                // role по умолчанию 'resident' задается в базе, поэтому в запросе можно не указывать
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password, full_name, apartment, phone, verify_code_hash, verify_expires, is_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$email, $hash, $fullName, $apartment, $phone, $codeHash, $expires]);
            }

            // Если не было ошибок выше (например, с уже существующим юзером)
            if (!$error) {
                // Отправляем письмо
                try {
                    $sent = sendVerificationCode($email, (string)$code);
                    
                    if ($sent) {
                        $pdo->commit();
                        // Очищаем весь буфер перед редиректом
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        // Переход на подтверждение
                        header("Location: verify.php?email=" . urlencode($email));
                        exit;
                    } else {
                        throw new Exception("Не удалось отправить письмо.");
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Ошибка отправки письма: " . $e->getMessage();
                }
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Логируем реальную ошибку для отладки
            error_log("Registration DB Error: " . $e->getMessage());
            $error = "Ошибка базы данных: " . $e->getMessage();
            // Для отладки можно раскомментировать: echo $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Регистрация в ТСЖ</title>
<link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
  <style>
      /* Небольшие стили для формы, если style_new.css не подхватит */
      .form-group { margin-bottom: 15px; }
      .form-group span { display: block; margin-bottom: 5px; font-weight: bold; }
      .form-group input { width: 100%; padding: 8px; box-sizing: border-box; }
      .error-msg { color: red; margin-bottom: 15px; }
  </style>
</head>
<body>
  <?php render_header(); ?>
  <div class="container">
    <h1>Регистрация жильца</h1>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" class="form">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'] ?? '')?>">

      <!-- Основные данные -->
      <div class="form-group">
        <label>
            <span>Email *</span>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label>
      </div>

      <div class="form-group">
        <label>
            <span>Пароль *</span>
            <input type="password" name="password" required minlength="8">
        </label>
      </div>

      <!-- Данные для ТСЖ -->
      <div class="form-group">
        <label>
            <span>ФИО (Полностью) *</span>
            <input type="text" name="full_name" required placeholder="Иванов Иван Иванович" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </label>
      </div>

      <div class="form-group">
        <label>
            <span>Номер квартиры *</span>
            <input type="text" name="apartment" required placeholder="42" value="<?= htmlspecialchars($_POST['apartment'] ?? '') ?>">
        </label>
      </div>

      <div class="form-group">
        <label>
            <span>Телефон</span>
            <input type="tel" name="phone" placeholder="+7 (999) 000-00-00" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </label>
      </div>

      <button type="submit">Зарегистрироваться</button>

      <p style="margin-top: 15px;">
          Уже есть аккаунт? <a href="login.php">Войти</a>
      </p>
    </form>
  </div>
</body>
</html>
