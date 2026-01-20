<?php
// forgot-password.php - ИСПРАВЛЕННЫЙ ВАРИАНТ

// ВКЛЮЧАЕМ ОТЛАДКУ
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Стартуем сессию ПЕРВЫМ делом
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Определяем абсолютный путь к корню
define('BASE_PATH', dirname(__FILE__));

// Отладочная информация
echo "<!-- Debug: BASE_PATH = " . BASE_PATH . " -->\n";
echo "<!-- Debug: Current dir = " . getcwd() . " -->\n";

// Проверяем существование файлов перед подключением
$required_files = [
    BASE_PATH . '/inc/header.php',
    BASE_PATH . '/inc/init.php',
    BASE_PATH . '/src/db.php',
    BASE_PATH . '/src/mail.php'
];

foreach ($required_files as $file) {
    echo "<!-- Debug: Checking $file -->\n";
    if (!file_exists($file)) {
        die("<pre style='color:red'>ERROR: File not found: $file\n" .
            "Available files in " . dirname($file) . ":\n" .
            print_r(scandir(dirname($file)), true) . "</pre>");
    }
}

// Теперь подключаем файлы
require_once BASE_PATH . '/inc/header.php';
require_once BASE_PATH . '/inc/init.php';
require_once BASE_PATH . '/src/db.php';
require_once BASE_PATH . '/src/mail.php';

echo "<!-- Debug: All files loaded successfully -->\n";

// Проверяем подключение к БД
if (!isset($pdo)) {
    die("<pre style='color:red'>ERROR: \$pdo is not defined after including db.php</pre>");
}

echo "<!-- Debug: Database connection established -->\n";

// Функция verifyTurnstile если её нет
if (!function_exists('verifyTurnstile')) {
    function verifyTurnstile($response, $secret) {
        // Заглушка для теста
        return !empty($response);
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    echo "<!-- Debug: POST request received -->\n";
    
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // Простая валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат email";
    }
    
    // Проверка Turnstile (если есть)
    if (!$error && isset($_POST['cf-turnstile-response'])) {
        // Замените на реальные ключи
        $turnstile_response = $_POST['cf-turnstile-response'];
        $secret_key = "YOUR_SECRET_KEY_HERE";
        
        if (!verifyTurnstile($turnstile_response, $secret_key)) {
            $error = "Пожалуйста, подтвердите, что вы не робот";
        }
    }
    
    if (!$error) {
        try {
            echo "<!-- Debug: Checking user in database -->\n";
            
            // Проверяем существование пользователя
            $stmt = $pdo->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<!-- Debug: User found: " . htmlspecialchars($user['email'] ?? 'unknown') . " -->\n";
                
                if (empty($user['is_verified']) || $user['is_verified'] == 0) {
                    $error = "Сначала подтвердите ваш email адрес";
                } else {
                    // Проверяем количество запросов за последние 24 часа
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM password_resets 
                        WHERE user_id = ? AND created_at > datetime('now', '-1 day')
                    ");
                    $stmt->execute([$user['id']]);
                    $attempts = $stmt->fetch()['count'];
                    
                    if ($attempts >= 3) {
                        $error = "Слишком много запросов сброса пароля. Попробуйте завтра.";
                    } else {
                        // Генерируем токен
                        $token = bin2hex(random_bytes(32));
                        $token_hash = password_hash($token, PASSWORD_DEFAULT);
                        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 час
                        
                        // Сохраняем токен в БД
                        $stmt = $pdo->prepare("
                            INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $success_insert = $stmt->execute([
                            $user['id'],
                            $token_hash,
                            $expires,
                            $_SERVER['REMOTE_ADDR'],
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        if ($success_insert) {
                            // Формируем ссылку для сброса
                            $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . 
                                          urlencode($token) . "&email=" . urlencode($email);
                            
                            echo "<!-- Debug: Reset link: $reset_link -->\n";
                            
                            // Отправляем письмо
                            if (function_exists('sendPasswordResetEmail')) {
                                $sent = sendPasswordResetEmail($email, $user['full_name'], $reset_link);
                                
                                if ($sent) {
                                    $success = "Инструкции по восстановлению пароля отправлены на ваш email.";
                                } else {
                                    $error = "Не удалось отправить письмо. Попробуйте позже.";
                                }
                            } else {
                                // Для отладки показываем ссылку
                                $success = "Ссылка для сброса пароля: <a href=\"$reset_link\">$reset_link</a>";
                            }
                        } else {
                            $error = "Ошибка при сохранении токена сброса";
                        }
                    }
                }
            } else {
                // Для безопасности показываем одинаковое сообщение
                $success = "Если email зарегистрирован, инструкции будут отправлены.";
                echo "<!-- Debug: User not found for email: $email -->\n";
            }
            
        } catch (PDOException $e) {
            $error = "Ошибка базы данных. Пожалуйста, попробуйте позже.";
            echo "<!-- Debug: PDO Exception: " . htmlspecialchars($e->getMessage()) . " -->\n";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="email"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .success {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1>Восстановление пароля</h1>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email адрес:</label>
            <input type="email" id="email" name="email" required 
                   placeholder="Ваш email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <!-- Cloudflare Turnstile (если используется) -->
        <?php if (defined('CF_TURNSTILE_SITE_KEY')): ?>
        <div class="cf-turnstile" data-sitekey="<?php echo CF_TURNSTILE_SITE_KEY; ?>"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php endif; ?>
        
        <button type="submit">Отправить ссылку для сброса</button>
    </form>
    <?php endif; ?>
    
    <p style="margin-top: 20px;">
        <a href="login.php">Вернуться к входу</a> | 
        <a href="register.php">Регистрация</a>
    </p>
    
    <!-- Отладочная информация (скрытая) -->
    <div style="display:none; background:#f0f0f0; padding:10px; margin-top:20px; font-size:12px;">
        <h3>Debug Info:</h3>
        <p>BASE_PATH: <?php echo BASE_PATH; ?></p>
        <p>Current file: <?php echo __FILE__; ?></p>
        <p>Session ID: <?php echo session_id(); ?></p>
        <p>Database: <?php echo isset($pdo) ? 'Connected' : 'Not connected'; ?></p>
    </div>
</body>
</html>
