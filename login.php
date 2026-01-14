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
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
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
    <link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
    <!-- Подключение Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
    <style>
        .msg { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
        .cf-turnstile-container { 
            margin: 20px 0; 
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
            min-height: 65px;
        }
        #turnstile-widget {
            width: 100%;
        }
        form label {
            display: block;
            margin-bottom: 15px;
        }
        form label input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<?php render_header(); ?>
<div class="container">
    <h1>Вход</h1>
    
    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" id="login-form">
        <label>Email <input type="email" name="email" required></label>
        <label>Пароль <input type="password" name="password" required></label>
        
        <!-- Cloudflare Turnstile Widget -->
        <div class="cf-turnstile-container">
            <div id="turnstile-widget"></div>
        </div>
        
        <button type="submit" id="submit-btn">Войти</button>
    </form>
    <p>Нет аккаунта? <a href="register.php">Регистрация</a></p>
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
