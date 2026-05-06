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

// Функция проверки Turnstile
if (!function_exists('verifyTurnstile')) {
    function verifyTurnstile(string $secretKey, string $responseToken): array
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        $data = [
            'secret'   => $secretKey,
            'response' => $responseToken,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);

        set_error_handler(function () { return true; });
        $result = file_get_contents($url, false, $context);
        restore_error_handler();

        if ($result === false) {
            error_log("Turnstile API недоступна или произошла ошибка сети");
            return ['success' => false, 'error' => 'network_error'];
        }

        $decoded = json_decode($result, true);
        if ($decoded === null) {
            error_log("Ошибка декодирования JSON от Turnstile: " . $result);
            return ['success' => false, 'error' => 'json_decode_error'];
        }

        return $decoded;
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Получаем и чистим данные
    $csrf_post            = $_POST['csrf'] ?? '';
    $email                = strtolower(trim($_POST['email'] ?? ''));
    $password             = $_POST['password'] ?? '';
    $password_confirm     = $_POST['password_confirm'] ?? '';
    $cf_turnstile_response = $_POST['cf-turnstile-response'] ?? '';

    // Поля для ТСЖ — обрезаем пробелы
    $fullName  = trim($_POST['full_name'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    // [ИСПРАВЛЕНО] Телефон: убираем всё кроме цифр, пробелов, +, -, (, )
    $phone = preg_replace('/[^\d\s\+\-\(\)]/', '', trim($_POST['phone'] ?? ''));

    // 2. Проверка CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf_post)) {
        die("Ошибка безопасности (CSRF). Обновите страницу.");
    }

    // 3. Валидация Turnstile
    if (empty($cf_turnstile_response)) {
        $error = "Пожалуйста, пройдите проверку безопасности.";
    } else {
        $turnstileResult = verifyTurnstile(TURNSTILE_SECRET_KEY, $cf_turnstile_response);

        if (!$turnstileResult['success']) {
            // [ИСПРАВЛЕНО] Отдельное сообщение при сетевой ошибке Cloudflare
            if (($turnstileResult['error'] ?? '') === 'network_error') {
                $error = "Сервис проверки безопасности временно недоступен. Попробуйте позже.";
            } else {
                $error = "Проверка безопасности не пройдена. Пожалуйста, попробуйте снова.";
            }
        }
    }

    // 4. Валидация полей
    if (!$error) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Некорректный email.";
        } elseif (strlen($email) > 255) {
            // [ИСПРАВЛЕНО] Ограничение длины email
            $error = "Email слишком длинный.";
        } elseif (strlen($password) < 8) {
            $error = "Пароль должен быть не менее 8 символов.";
        } elseif ($password !== $password_confirm) {
            $error = "Пароли не совпадают.";
        } elseif (empty($fullName)) {
            $error = "Пожалуйста, заполните ФИО.";
        } elseif (strlen($fullName) > 255) {
            $error = "ФИО слишком длинное (максимум 255 символов).";
        } elseif (empty($apartment)) {
            $error = "Пожалуйста, укажите номер квартиры.";
        } elseif (!preg_match('/^\d{1,6}[а-яёА-ЯЁa-zA-Z-A-a-а-А-Б-б-В-в]?$/', $apartment)) {
            $error = "Некорректный номер квартиры (например: 42 или 12А).";
        } elseif (!empty($phone) && strlen($phone) > 20) {
            $error = "Некорректный номер телефона.";
        }
    }

    // 5. Если ошибок нет — пробуем зарегистрировать
    if (!$error) {
        try {
            // Проверяем, есть ли пользователь с таким email
            $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            // Генерируем данные для верификации
            $hash     = password_hash($password, PASSWORD_DEFAULT);
            $code     = (string)random_int(100000, 999999); // [ИСПРАВЛЕНО] Сразу строка
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $expires  = time() + 15 * 60; // 15 минут


            $pdo->beginTransaction();

            if ($exists) {
                if ((int)$exists['is_verified'] === 1) {
                    $pdo->rollBack();
                    $error = "Пользователь с таким email уже зарегистрирован.";
                } else {
                    // Пользователь есть, но не подтверждён — обновляем данные
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
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password, full_name, apartment, phone, verify_code_hash, verify_expires, is_verified, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$email, $hash, $fullName, $apartment, $phone, $codeHash, $expires]);
            }

            // Отправляем письмо (только если выше не поставили $error)
            if (!$error) {
                try {
                    $sent = sendVerificationCode($email, $code);

                    if ($sent) {
                        // Сохраняем email в сессию перед редиректом
                        $_SESSION['verify_email'] = $email;
                        $pdo->commit();
                        // Очищаем буфер перед редиректом
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Registration DB Error: " . $e->getMessage());
            $error = "Ошибка базы данных. Пожалуйста, попробуйте позже.";
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <?php render_head_content(); ?>
  <title>Регистрация в ТСЖ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad" defer></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
  <?php render_header(); ?>
  <div class="w-11/12 max-w-xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Регистрация жильца</h1>

    <?php if ($error): ?>
        <div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" id="registration-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
      <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>Email *</span>
          <input type="email" name="email" required maxlength="255"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>Пароль *</span>
          <input type="password" name="password" required minlength="8" autocomplete="new-password"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>Повторите пароль *</span>
          <input type="password" name="password_confirm" required autocomplete="new-password"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>ФИО (Полностью) *</span>
          <input type="text" name="full_name" required maxlength="255"
                 placeholder="Иванов Иван Иванович"
                 value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>Номер квартиры *</span>
          <input type="text" name="apartment" required maxlength="7"
                 placeholder="42 или 12А"
                 title="Введите номер квартиры: цифры и необязательная буква (например 42 или 12А)"
                 value="<?= htmlspecialchars($_POST['apartment'] ?? '') ?>"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
          <span>Телефон</span>
          <input type="tel" name="phone" maxlength="20"
                 placeholder="+7 (999) 000-00-00"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <!-- Cloudflare Turnstile Widget -->
      <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4 min-h-[65px]">
        <div id="turnstile-widget"></div>
      </div>

      <button type="submit" id="submit-btn"
              class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">
        Зарегистрироваться
      </button>

      <p class="mt-4 text-sm text-slate-700">
        Уже есть аккаунт? <a class="text-blue-600 hover:underline" href="login.php">Войти</a>
      </p>
    </form>
  </div>

  <script>
  function onTurnstileLoad() {
      const form        = document.getElementById('registration-form');
      const tokenInput  = document.getElementById('cf-turnstile-response');
      const container   = document.getElementById('turnstile-widget');

      if (!container) {
          console.error('Контейнер Turnstile не найден');
          return;
      }

      turnstile.render('#turnstile-widget', {
          sitekey: '<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>',

          callback: function (token) {
              tokenInput.value = token;
              console.log('Turnstile пройден');
          },

          'error-callback': function () {
              tokenInput.value = '';
              console.warn('Ошибка Turnstile');
          },

          'expired-callback': function () {
              tokenInput.value = '';
              console.warn('Токен Turnstile истёк');
          },

          theme: 'light',
          size: 'normal'
      });

      form.addEventListener('submit', function (e) {
          if (!tokenInput.value) {
              e.preventDefault();
              alert('Пожалуйста, пройдите проверку безопасности.');
              return false;
          }
      });
  }
  </script>
</body>
</html>
