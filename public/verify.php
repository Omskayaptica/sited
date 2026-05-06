<?php
// public/verify.php
ob_start();

require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

$error = '';
$success = '';
$show_form = true;

// Email из сессии (более безопасно)
$email = $_SESSION['verify_email'] ?? $_GET['email'] ?? '';

if (empty($email)) {
    die("Email не указан. <a href='register.php'>Вернуться на регистрацию</a>");
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF защита
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности (CSRF).");
    }

    $code = trim($_POST['code'] ?? '');

    // Валидация
    if (empty($code)) {
        $error = "Введите код подтверждения.";
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = "Код должен быть ровно 6 цифр.";
    } else {
        try {
            // Получаем пользователя
            $stmt = $pdo->prepare("
                SELECT id, verify_code_hash, verify_expires, is_verified, verify_attempts 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "Пользователь с таким email не найден.";
            } elseif ($user['is_verified'] == 1) {
                $success = "Аккаунт уже подтвержден.";
                $show_form = false;
            } elseif ($user['verify_attempts'] >= 5) {
                $error = "Слишком много неверных попыток. Попробуйте позже.";
            } elseif (time() > $user['verify_expires']) {
                $error = "Срок действия кода истек. Запросите новый при входе.";
            } elseif (!password_verify($code, $user['verify_code_hash'])) {
                // Увеличиваем счётчик попыток
                $stmt = $pdo->prepare("UPDATE users SET verify_attempts = verify_attempts + 1 WHERE id = ?");
                $stmt->execute([$user['id']]);
                $error = "Неверный код подтверждения.";
            } else {
                // ВСЁ ХОРОШО: активируем аккаунт
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        "UPDATE users SET is_verified = 1, verify_code_hash = NULL, 
                         verify_expires = NULL, verify_attempts = 0 WHERE id = ?"
                    );
                    $stmt->execute([$user['id']]);
                    $pdo->commit();
                    
                    // Очищаем сессию
                    unset($_SESSION['verify_email']);
                    
                    // Редирект
                    while (ob_get_level()) ob_end_clean();
                    header("Location: login.php?verified=1");
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Ошибка при активации аккаунта.";
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
  <?php render_head_content(); ?>
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
        <div class="mt-4 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mt-4 mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            <?= htmlspecialchars($success) ?>
            <a href="login.php" class="font-semibold underline">Перейти к входу</a>
        </div>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <form method="POST" action="verify.php" class="mt-6 space-y-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        
        <div>
            <label class="block text-sm font-semibold text-slate-700">Код подтверждения:</label>
            <input type="text" name="code" required placeholder="123456" autocomplete="off" 
                   pattern="\d{6}" title="6 цифр" maxlength="6"
                   class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </div>

        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Подтвердить</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>