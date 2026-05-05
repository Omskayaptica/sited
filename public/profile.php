<?php
// public/profile.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$success = '';
$error_info = '';
$error_pass = '';

// Получаем актуальные данные пользователя из БД
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- ОБНОВЛЕНИЕ КОНТАКТНЫХ ДАННЫХ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности. Обновите страницу.");
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name)) {
        $error_info = "ФИО не может быть пустым.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $userId]);

        // Обновляем сессию
        $_SESSION['full_name'] = $full_name;
        $user['full_name'] = $full_name;
        $user['phone'] = $phone;

        $success = "info";
    }
}

// --- СМЕНА ПАРОЛЯ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности. Обновите страницу.");
    }

    $old_password     = $_POST['old_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!password_verify($old_password, $user['password'])) {
        $error_pass = "Текущий пароль введён неверно.";
    } elseif (strlen($new_password) < 8) {
        $error_pass = "Новый пароль должен быть не менее 8 символов.";
    } elseif ($new_password !== $confirm_password) {
        $error_pass = "Новые пароли не совпадают.";
    } elseif ($old_password === $new_password) {
        $error_pass = "Новый пароль должен отличаться от текущего.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        $success = "pass";
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — ТСЖ «Наш Дом»</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-2xl mx-auto my-8 space-y-6">

    <!-- Заголовок -->
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Личный кабинет</h1>
        <p class="mt-1 text-sm text-slate-500">Кв. <?= htmlspecialchars($user['apartment']) ?> · <?= htmlspecialchars($user['email']) ?></p>
    </div>

    <!-- Уведомления об успехе -->
    <?php if ($success === 'info'): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✓ Данные успешно обновлены.
        </div>
    <?php elseif ($success === 'pass'): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✓ Пароль успешно изменён.
        </div>
    <?php endif; ?>

    <!-- БЛОК 1: Контактные данные -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">Контактные данные</h2>
        </div>
        <div class="px-6 py-5">
            <?php if ($error_info): ?>
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= htmlspecialchars($error_info) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="update_info" value="1">

                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        ФИО <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="full_name"
                        required
                        value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Телефон
                    </label>
                    <input
                        type="tel"
                        name="phone"
                        placeholder="+7 (999) 000-00-00"
                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                </div>

                <!-- Нередактируемые поля -->
                <div class="grid grid-cols-2 gap-4 pt-1">
                    <div>
                        <label class="block text-sm font-medium text-slate-500">Email</label>
                        <p class="mt-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            <?= htmlspecialchars($user['email']) ?>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-500">Квартира</label>
                        <p class="mt-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            № <?= htmlspecialchars($user['apartment']) ?>
                        </p>
                    </div>
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                    >
                        Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- БЛОК 2: Смена пароля -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">Смена пароля</h2>
        </div>
        <div class="px-6 py-5">
            <?php if ($error_pass): ?>
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= htmlspecialchars($error_pass) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="update_password" value="1">

                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Текущий пароль <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="password"
                        name="old_password"
                        required
                        autocomplete="current-password"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Новый пароль <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="password"
                        name="new_password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        id="new_password"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                    <p class="mt-1 text-xs text-slate-400">Не менее 8 символов</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Повторите новый пароль <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                        id="confirm_password"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                    <!-- Подсказка о совпадении паролей -->
                    <p id="pass_match" class="mt-1 text-xs hidden"></p>
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        id="pass_submit"
                        class="inline-flex items-center rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 transition-colors"
                    >
                        Изменить пароль
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- БЛОК 3: Информация об аккаунте (только чтение) -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">Информация об аккаунте</h2>
        </div>
        <div class="px-6 py-5">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Роль</dt>
                    <dd class="font-medium text-slate-900">
                        <?= $user['role'] === 'admin' ? 'Администратор' : 'Житель' ?>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Статус почты</dt>
                    <dd>
                        <?php if ($user['is_verified']): ?>
                            <span class="inline-flex items-center gap-1 text-green-700 font-medium">
                                <span>✓</span> Подтверждена
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-amber-600 font-medium">
                                <span>⚠</span> Не подтверждена
                            </span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Дата регистрации</dt>
                    <dd class="font-medium text-slate-900">
                        <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

</div>

<script>
// Клиентская проверка совпадения паролей
const newPass     = document.getElementById('new_password');
const confirmPass = document.getElementById('confirm_password');
const matchHint   = document.getElementById('pass_match');
const submitBtn   = document.getElementById('pass_submit');

function checkPasswords() {
    const val1 = newPass.value;
    const val2 = confirmPass.value;

    if (!val2) {
        matchHint.classList.add('hidden');
        submitBtn.disabled = false;
        return;
    }

    matchHint.classList.remove('hidden');

    if (val1 === val2) {
        matchHint.textContent = '✓ Пароли совпадают';
        matchHint.className = 'mt-1 text-xs text-green-600';
        submitBtn.disabled = false;
    } else {
        matchHint.textContent = '✗ Пароли не совпадают';
        matchHint.className = 'mt-1 text-xs text-red-500';
        submitBtn.disabled = true;
    }
}

newPass.addEventListener('input', checkPasswords);
confirmPass.addEventListener('input', checkPasswords);
</script>

</body>
</html>