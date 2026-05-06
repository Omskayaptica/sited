<?php
// public/admin-readings.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    die("Доступ запрещен.");
}

// Фильтры
$month_filter = $_GET['month'] ?? '';
$apartment_filter = $_GET['apartment'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if (!empty($month_filter)) {
    // SQLite синтаксис strftime
    $where[] = "strftime('%Y-%m', r.month_year) = ?";
    $params[] = $month_filter;
}

if (!empty($apartment_filter)) {
    $where[] = "r.apartment = ?";
    $params[] = $apartment_filter;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 1. Считаем общее количество
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM meter_readings r 
    JOIN users u ON r.user_id = u.id 
    $where_clause
");
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total / $per_page);

$sql = "
    SELECT r.*, u.full_name, u.email, u.phone
    FROM meter_readings r 
    JOIN users u ON r.user_id = u.id 
    $where_clause
    ORDER BY r.reading_date DESC 
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params); 
$readings = $stmt->fetchAll();

$months_stmt = $pdo->query("
    SELECT DISTINCT strftime('%Y-%m', month_year) as month 
    FROM meter_readings 
    ORDER BY month DESC
");
$available_months = $months_stmt->fetchAll();

$apartments_stmt = $pdo->query("SELECT DISTINCT apartment FROM meter_readings ORDER BY apartment");
$available_apartments = $apartments_stmt->fetchAll();
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <?php render_head_content(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал показаний</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-7xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Журнал показаний счетчиков</h1>
    
    <!-- Фильтры -->
    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <form method="get" action="" class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="min-w-[220px]">
                <label class="block text-sm font-semibold text-slate-700">Месяц</label>
                <select name="month" onchange="this.form.submit()" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Все месяцы</option>
                    <?php foreach ($available_months as $m): ?>
                        <option value="<?= $m['month'] ?>" 
                            <?= ($month_filter == $m['month']) ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m['month'] . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="min-w-[220px]">
                <label class="block text-sm font-semibold text-slate-700">Квартира</label>
                <select name="apartment" onchange="this.form.submit()" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">Все квартиры</option>
                    <?php foreach ($available_apartments as $apt): ?>
                        <option value="<?= $apt['apartment'] ?>" 
                            <?= ($apartment_filter == $apt['apartment']) ? 'selected' : '' ?>>
                            Кв. <?= $apt['apartment'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="submit" class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-white font-semibold hover:bg-emerald-700">Применить</button>
                <a href="?" class="inline-flex items-center justify-center rounded-md bg-slate-200 px-4 py-2 text-slate-800 font-semibold hover:bg-slate-300">Сбросить</a>
            </div>
            
            <!-- Кнопка экспорта -->
            <button type="button" class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-white font-semibold hover:bg-emerald-700" onclick="exportToExcel()">
                📊 Экспорт в Excel
            </button>
        </form>
    </div>
    
    <!-- Информация о фильтрах -->
    <p class="mt-4 text-slate-700">Найдено записей: <strong class="text-slate-900"><?= $total ?></strong></p>
    
    <!-- Таблица -->
    <div class="mt-4 overflow-x-auto">
    <table id="readingsTable" class="w-full border-collapse text-sm">
        <thead class="bg-slate-100 text-slate-900">
            <tr>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Дата отправки</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Месяц</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Кв.</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Жилец</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Телефон</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">ХВС (м³)</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">ГВС (м³)</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Электро. (кВт·ч)</th>
                <th class="sticky top-0 bg-slate-100 border border-slate-200 px-3 py-2 text-left font-semibold">Суммарно (м³)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($readings as $row): 
                $total_water = $row['cold_water'] + $row['hot_water'];
            ?>
            <tr class="odd:bg-white even:bg-slate-50 hover:bg-slate-100">
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= date('d.m.Y H:i', strtotime($row['reading_date'])) ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= date('m.Y', strtotime($row['month_year'])) ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($row['apartment']) ?></td>
                <td class="border border-slate-200 px-3 py-2"><?= htmlspecialchars($row['full_name']) ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= number_format($row['cold_water'], 3, ',', ' ') ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= number_format($row['hot_water'], 3, ',', ' ') ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap"><?= number_format($row['electricity'], 3, ',', ' ') ?></td>
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap font-semibold"><?= number_format($total_water, 3, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($readings)): ?>
                <tr><td class="border border-slate-200 px-3 py-4 text-center text-slate-700" colspan="9">Показаний нет.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    
    <!-- Пагинация -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
        <?php if ($page > 1): ?>
            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">««</a>
            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i == $page): ?>
                <span class="inline-flex items-center justify-center rounded-md border border-blue-600 bg-blue-600 px-3 py-1.5 text-white font-semibold"><?= $i ?></span>
            <?php else: ?>
                <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">»»</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function exportToExcel() {
    let table = document.getElementById('readingsTable');
    let html = table.outerHTML;
    
    // Создаем Blob и скачиваем
    let blob = new Blob(['\ufeff', html], {
        type: 'application/vnd.ms-excel'
    });
    
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Показания_счетчиков_<?= date('Y-m-d') ?>.xls';
    link.click();
}
</script>
</body>
</html>