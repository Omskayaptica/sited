<?php
// forgot-password.php - Восстановление пароля

// Стартуем сессию ПЕРВЫМ делом
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Определяем абсолютный путь к корню
define('BASE_PATH', dirname(__FILE__));

// Проверяем существование файлов перед подключением
$required_files = [
    BASE_PATH . '/inc/header.php',
    BASE_PATH . '/inc/init.php',
    BASE_PATH . '/src/db.php',
    BASE_PATH . '/src/mail.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Ошибка конфигурации приложения.");
    }
}

// Теперь подключаем файлы
require_once BASE_PATH . '/inc/header.php';
require_once BASE_PATH . '/inc/init.php';
require_once BASE_PATH . '/src/db.php';
require_once BASE_PATH . '/src/mail.php';

// Проверяем подключение к БД
if (!isset($pdo)) {
    die("Ошибка подключения к базе данных.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // Простая валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат email";
    }
    
    // Проверка Turnstile (если используется в продакшене)
    if (!$error && isset($_POST['cf-turnstile-response'])) {
        // Проверяем, определена ли функция и конфигурация доступна
        if (!function_exists('verifyTurnstile') || !defined('TURNSTILE_SECRET_KEY')) {
            // В продакшене это должно быть настроено правильно
            $error = "Проверка безопасности временно недоступна. Пожалуйста, попробуйте позже.";
        } else {
            $turnstile_response = $_POST['cf-turnstile-response'];
            $turnstileResult = verifyTurnstile(TURNSTILE_SECRET_KEY, $turnstile_response);
            
            if (!$turnstileResult['success']) {
                $error = "Проверка безопасности не пройдена. Пожалуйста, попробуйте снова.";
            }
        }
    }
    
    if (!$error) {
        try {
            // Проверяем существование пользователя
            $stmt = $pdo->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
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
            }
            
        } catch (PDOException $e) {
            $error = "Ошибка базы данных. Пожалуйста, попробуйте позже.";
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
    <div class="w-11/12 max-w-xl mx-auto my-10 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Восстановление пароля</h1>
    
    <?php if ($error): ?>
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <form method="POST" action="">
        <div class="mt-4">
            <label for="email" class="block text-sm font-semibold text-slate-700">Email адрес:</label>
            <input type="email" id="email" name="email" required 
                   placeholder="Ваш email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                   class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </div>
        
        <!-- Cloudflare Turnstile (если используется) -->
        <?php if (defined('TURNSTILE_SITE_KEY')): ?>
        <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4">
            <div class="cf-turnstile" data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>"></div>
        </div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php endif; ?>
        
        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Отправить ссылку для сброса</button>
    </form>
    <?php endif; ?>
    
    <p class="mt-6 text-sm text-slate-700">
        <a class="text-blue-600 hover:underline" href="login.php">Вернуться к входу</a> | 
        <a class="text-blue-600 hover:underline" href="register.php">Регистрация</a>
    </p>
    </div>
</body>
</html>
