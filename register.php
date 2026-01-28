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
function verifyTurnstile($secretKey, $responseToken) {
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    $data = [
        'secret' => $secretKey,
        'response' => $responseToken,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 5  // Таймаут 5 секунд
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Обработка ошибок при HTTP-запросе
    set_error_handler(function() { return true; });
    $result = file_get_contents($url, false, $context);
    restore_error_handler();
    
    // Проверка результата
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

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Получаем и чистим данные
    $csrf_post = $_POST['csrf'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $cf_turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    
    // Новые поля для ТСЖ
    $fullName = trim($_POST['full_name'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // 2. Валидация
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf_post)) {
        die("Ошибка безопасности (CSRF). Обновите страницу.");
    }

    // 3. Валидация Turnstile 
    if (empty($cf_turnstile_response)) {
        $error = "Пожалуйста, пройдите проверку безопасности.";
    } else {
        $turnstileResult = verifyTurnstile(TURNSTILE_SECRET_KEY, $cf_turnstile_response);
        
        if (!$turnstileResult['success']) {
            $error = "Проверка безопасности не пройдена. Пожалуйста, попробуйте снова.";
            // Для отладки можно добавить:
            // error_log("Turnstile error: " . print_r($turnstileResult, true));
        }
    }
   if (!$error) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный email.";
    } elseif (strlen($password) < 8) {
        $error = "Пароль должен быть не менее 8 символов.";
    } elseif (empty($fullName) || empty($apartment)) {
        $error = "Пожалуйста, заполните ФИО и номер квартиры.";
    }
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Подключение Turnstile - УБЕРИТЕ async defer для контроля загрузки -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
  <?php render_header(); ?>
  <div class="w-11/12 max-w-xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Регистрация жильца</h1>

    <?php if ($error): ?>
        <div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" class="form" id="registration-form">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'] ?? '')?>">

      <!-- Основные данные -->
      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
            <span>Email *</span>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
            <span>Пароль *</span>
            <input type="password" name="password" required minlength="8" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <!-- Данные для ТСЖ -->
      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
            <span>ФИО (Полностью) *</span>
            <input type="text" name="full_name" required placeholder="Иванов Иван Иванович" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
            <span>Номер квартиры *</span>
            <input type="text" name="apartment" required placeholder="42" value="<?= htmlspecialchars($_POST['apartment'] ?? '') ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-semibold text-slate-700">
            <span>Телефон</span>
            <input type="tel" name="phone" placeholder="+7 (999) 000-00-00" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
      </div>

      <!-- Cloudflare Turnstile Widget -->
      <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4 min-h-[65px]">
          <div id="turnstile-widget" class="w-full"></div>
      </div>

      <button type="submit" id="submit-btn" class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Зарегистрироваться</button>

      <p class="mt-4 text-sm text-slate-700">
          Уже есть аккаунт? <a class="text-blue-600 hover:underline" href="login.php">Войти</a>
      </p>
    </form>
  </div>

  <script>
  // Инициализация Turnstile с задержкой
  document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('registration-form');
      const submitBtn = document.getElementById('submit-btn');
      let turnstileWidget = null;
      let turnstileToken = '';
      
      // Функция инициализации Turnstile
      function initTurnstile() {
          // Проверяем, не был ли уже инициализирован виджет
          if (document.querySelector('.cf-turnstile iframe')) {
              console.log('Turnstile уже инициализирован');
              return;
          }
          
          // Очищаем контейнер
          const container = document.getElementById('turnstile-widget');
          if (container) {
              container.innerHTML = '';
              
              // Инициализируем Turnstile
              if (typeof turnstile !== 'undefined') {
                  turnstileWidget = turnstile.render('#turnstile-widget', {
                      sitekey: '<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>',
                      callback: function(token) {
                          turnstileToken = token;
                          console.log('Turnstile пройден, токен получен');
                          // Создаем скрытое поле для токена
                          let hiddenInput = document.querySelector('input[name="cf-turnstile-response"]');
                          if (!hiddenInput) {
                              hiddenInput = document.createElement('input');
                              hiddenInput.type = 'hidden';
                              hiddenInput.name = 'cf-turnstile-response';
                              form.appendChild(hiddenInput);
                          }
                          hiddenInput.value = token;
                      },
                      'error-callback': function() {
                          console.log('Ошибка Turnstile');
                          turnstileToken = '';
                      },
                      'expired-callback': function() {
                          console.log('Turnstile токен истек');
                          turnstileToken = '';
                      },
                      theme: 'light',
                      size: 'normal'
                  });
                  
                  console.log('Turnstile инициализирован');
              } else {
                  console.error('Turnstile API не загружен');
                  // Если Turnstile не загрузился, показываем сообщение
                  container.innerHTML = '<div style="color: red;">Ошибка загрузки проверки безопасности. Пожалуйста, обновите страницу.</div>';
              }
          }
      }
      
      // Ждем загрузки Turnstile API
      if (typeof turnstile !== 'undefined') {
          // Если API уже загружено
          setTimeout(initTurnstile, 100);
      } else {
          // Если API еще не загружено, ждем
          let attempts = 0;
          const waitForTurnstile = setInterval(function() {
              attempts++;
              if (typeof turnstile !== 'undefined') {
                  clearInterval(waitForTurnstile);
                  initTurnstile();
              } else if (attempts > 50) { // Ждем максимум 5 секунд (50 * 100ms)
                  clearInterval(waitForTurnstile);
                  console.error('Turnstile API не загрузилось за 5 секунд');
                  document.getElementById('turnstile-widget').innerHTML = 
                      '<div style="color: orange;">Проверка безопасности временно недоступна. Пожалуйста, попробуйте позже.</div>';
              }
          }, 100);
      }
      
      // Проверка при отправке формы
      form.addEventListener('submit', function(e) {
          if (!turnstileToken) {
              e.preventDefault();
              alert('Пожалуйста, пройдите проверку безопасности (нажмите на квадратик).');
              return false;
          }
          
          // Дополнительная проверка скрытого поля
          const hiddenInput = document.querySelector('input[name="cf-turnstile-response"]');
          if (!hiddenInput || !hiddenInput.value) {
              e.preventDefault();
              alert('Ошибка проверки безопасности. Обновите страницу и попробуйте снова.');
              return false;
          }
          
          return true;
      });
  });
  </script>
</body>
</html>
