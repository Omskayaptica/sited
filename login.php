<?php
// public/login.php

// Буферизация вывода для безопасной отправки header()
ob_start();

session_start();
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/src/db.php';

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

$error = '';
$message = '';

if (isset($_GET['verified'])) {
    $message = "Email подтвержден! Теперь вы можете войти.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cf_turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    
    // 1. Проверка Turnstile
    if (empty($cf_turnstile_response)) {
        $error = "Пожалуйста, пройдите проверку безопасности.";
    } else {
        $turnstileResult = verifyTurnstile(TURNSTILE_SECRET_KEY, $cf_turnstile_response);
        
        if (!$turnstileResult['success']) {
            $error = "Проверка безопасности не пройдена. Пожалуйста, попробуйте снова.";
        }
    }
    
    // 2. Если Turnstile прошел, проверяем логин/пароль
    if (!$error) {
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
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в ТСЖ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Подключение Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Вход</h1>
    
    <?php if ($message): ?><div class="mt-4 mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" id="login-form">
        <label class="block mt-4 text-sm font-semibold text-slate-700">Email
            <input type="email" name="email" required class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
        <label class="block mt-4 text-sm font-semibold text-slate-700">Пароль
            <input type="password" name="password" required class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>
        
        <!-- Cloudflare Turnstile Widget -->
        <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4 min-h-[65px]">
            <div id="turnstile-widget" class="w-full"></div>
        </div>
        
        <button type="submit" id="submit-btn" class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Войти</button>
    </form>
    <p class="mt-4 text-sm text-slate-700">Нет аккаунта? <a class="text-blue-600 hover:underline" href="register.php">Регистрация</a></p>
    <p class="mt-2 text-sm text-slate-700">Забыли пароль? <a class="text-blue-600 hover:underline" href="forgot-password.php">Восстановить</a></p>
</div>

<script>
// Инициализация Turnstile с задержкой
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
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
