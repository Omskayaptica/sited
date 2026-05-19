<?php

ob_start();


require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/src/db.php';


if (isset($_SESSION['user_id'])) {
    while (ob_get_level()) ob_end_clean();
    header('Location: index.php');
    exit;
}

require_once '/var/www/mysite/inc/header.php';



const DUMMY_HASH = '$2y$12$invalidhashvaluethatwillnevermatchwithanypassword1234567';

$error   = '';
$message = '';

if (isset($_GET['verified'])) {
    $message = "Email подтверждён! Теперь вы можете войти.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("Ошибка безопасности. Пожалуйста, обновите страницу.");
    }

    $cf_turnstile_response = $_POST['cf-turnstile-response'] ?? '';


    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // 1. Валидация полей
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный email.";
    } elseif (empty($password)) {
        $error = "Введите пароль.";
    }

    // 2. Проверка Turnstile
    if (!$error && !SKIP_TURNSTILE_CHECK) {
        if (empty($cf_turnstile_response)) {
            $error = "Пожалуйста, пройдите проверку безопасности.";
        } else {
            $turnstileResult = verifyTurnstile(TURNSTILE_SECRET_KEY, $cf_turnstile_response);

            if (!$turnstileResult['success']) {
                if (($turnstileResult['error'] ?? '') === 'network_error') {
                    $error = "Сервис проверки безопасности временно недоступен. Попробуйте позже.";
                } else {
                    $error = "Проверка безопасности не пройдена. Попробуйте снова.";
                }
            }
        }
    }


    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, role, full_name, apartment, password, is_verified
                FROM users
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Проверяем пароль
            $passwordValid = $user && password_verify($password, $user['password']);

            if ($user && $passwordValid) {
                if ((int)$user['is_verified'] === 0) {
                    $error = "Пожалуйста, подтвердите ваш email перед входом.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['apartment'] = $user['apartment'];

                    while (ob_get_level()) ob_end_clean();
                    header('Location: index.php');
                    exit;
                }
            } else {

                $error = "Неверный email или пароль.";
            }

        } catch (PDOException $e) {
            error_log("Login DB error: " . $e->getMessage());
            $error = "Ошибка сервера. Попробуйте позже.";
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <?php render_head_content(); ?>
    <title>Вход — ТСЖ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (!SKIP_TURNSTILE_CHECK): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad" defer></script>
    <?php endif; ?>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Вход</h1>

    <?php if ($message): ?>
        <div class="mt-4 mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="login-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
        <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">

        <label class="block mt-4 text-sm font-semibold text-slate-700">
            Email
            <input type="email" name="email" required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>

        <label class="block mt-4 text-sm font-semibold text-slate-700">
            Пароль
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </label>

        <!-- Cloudflare Turnstile Widget -->
        <?php if (!SKIP_TURNSTILE_CHECK): ?>
        <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4 min-h-[65px]">
            <div id="turnstile-widget"></div>
        </div>
        <?php endif; ?>

        <button type="submit" id="submit-btn"
                class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">
            Войти
        </button>
    </form>

    <p class="mt-4 text-sm text-slate-700">
        Нет аккаунта? <a class="text-blue-600 hover:underline" href="register.php">Регистрация</a>
    </p>
    <p class="mt-2 text-sm text-slate-700">
        Забыли пароль? <a class="text-blue-600 hover:underline" href="forgot-password.php">Восстановить</a>
    </p>
</div>

<?php if (!SKIP_TURNSTILE_CHECK): ?>
<script>
function onTurnstileLoad() {
    const form       = document.getElementById('login-form');
    const tokenInput = document.getElementById('cf-turnstile-response');

    turnstile.render('#turnstile-widget', {
        sitekey: '<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>',

        callback: function (token) {
            tokenInput.value = token;
        },

        'error-callback': function () {
            tokenInput.value = '';
        },

        'expired-callback': function () {
            tokenInput.value = '';
        },

        theme: 'light',
        size: 'normal'
    });

    form.addEventListener('submit', function (e) {
        if (!tokenInput.value) {
            e.preventDefault();
            alert('Пожалуйста, пройдите проверку безопасности.');
        }
    });
}
</script>
<?php endif; ?>
</body>
</html>