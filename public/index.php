<?php
// public/index.php
ob_start();
require_once '/var/www/mysite/inc/init.php'; 
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Если не вошел — отправляем на логин
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$userName = $_SESSION['full_name'];
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Главная — ТСЖ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-5xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <div class="bg-slate-100/70 border border-slate-200 p-6 rounded-lg mb-5">
        <h1 class="text-2xl font-bold text-slate-900">Добро пожаловать, <?= htmlspecialchars($userName) ?>!</h1>
        <p class="mt-2 text-slate-700">Вы зашли в систему ТСЖ. Показания принимаются ежемесячно до 25 числа.</p>
    </div>

    <?php if ($role !== 'admin'): ?>
    <!-- Для жильца: Получить данные -->
    <?php
    // Получить данные
    $submitted = $pdo->prepare(
        "SELECT COUNT(*) FROM meter_readings 
         WHERE user_id=? AND month_year=?"
    );
    $submitted->execute([$_SESSION['user_id'], date('Y-m-01')]);
    $meterDone = $submitted->fetchColumn() > 0;

    $debt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM bills 
         WHERE user_id=? AND status='unpaid'"
    );
    $debt->execute([$_SESSION['user_id']]);
    $totalDebt = $debt->fetchColumn();

    $openReqs = $pdo->prepare(
        "SELECT COUNT(*) FROM requests 
         WHERE user_id=? AND status NOT IN ('done','rejected')"
    );
    $openReqs->execute([$_SESSION['user_id']]);
    $openCount = $openReqs->fetchColumn();
    ?>

    <!-- Блок статистики -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
        <div class="text-2xl"><?= $meterDone ? '✅' : '⚠️' ?></div>
        <div class="mt-1 text-sm font-semibold text-slate-700">Показания</div>
        <div class="text-xs text-slate-500">
          <?= $meterDone ? 'Сданы за '.date('F') : 'Не сданы' ?>
        </div>
      </div>
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold 
          <?= $totalDebt > 0 ? 'text-red-600' : 'text-green-600' ?>">
          <?= number_format($totalDebt, 0, '.', ' ') ?> ₽
        </div>
        <div class="mt-1 text-sm font-semibold text-slate-700">Задолженность</div>
      </div>
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center">
        <div class="text-2xl font-bold text-slate-700"><?= $openCount ?></div>
        <div class="mt-1 text-sm font-semibold text-slate-700">Открытых заявок</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-6">
        <?php if ($role === 'admin'): ?>
            <!-- КАРТОЧКИ ДЛЯ АДМИНА -->
            <div class="bg-white p-5 border border-amber-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Заявки жильцов</h3>
                <p class="text-sm text-slate-600 mb-5">Просмотр и ответ на новые жалобы и обращения.</p>
                <a href="admin-requests.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-medium hover:bg-blue-700">Перейти к списку</a>
            </div>

            <div class="bg-white p-5 border border-amber-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Показания счетчиков</h3>
                <p class="text-sm text-slate-600 mb-5">Сводная таблица по всем квартирам за текущий месяц.</p>
                <a href="admin-readings.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-medium hover:bg-blue-700">Открыть журнал</a>
            </div>

            <div class="bg-white p-5 border border-amber-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Управление домом</h3>
                <p class="text-sm text-slate-600 mb-5">Добавление новостей, работа со списками жильцов.</p>
                <a href="#" class="inline-flex items-center justify-center rounded-md bg-slate-300 px-4 py-2 text-slate-700 font-medium cursor-not-allowed">В разработке</a>
            </div>

        <?php else: ?>
            <!-- КАРТОЧКИ ДЛЯ ЖИЛЬЦА -->
            <div class="bg-white p-5 border border-slate-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Мои заявки</h3>
                <p class="text-sm text-slate-600 mb-5">Подать новую жалобу или посмотреть статус старых.</p>
                <a href="my-requests.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-medium hover:bg-blue-700">Открыть заявки</a>
            </div>

            <div class="bg-white p-5 border border-slate-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Передать показания</h3>
                <p class="text-sm text-slate-600 mb-5">Передать данные по воде и электричеству за текущий месяц.</p>
                <a href="meter-submit.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-medium hover:bg-blue-700">Сдать данные</a>
            </div>

            <div class="bg-white p-5 border border-slate-200 rounded-xl text-center transition hover:shadow-lg hover:-translate-y-1">
                <h3 class="text-lg font-semibold text-blue-600 mb-3">Мои платежи</h3>
                <p class="text-sm text-slate-600 mb-5">История начислений и оплата квитанций онлайн.</p>
                <a href="my-payments.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-medium hover:bg-blue-700">
                Перейти к оплате
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>