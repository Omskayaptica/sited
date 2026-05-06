<?php
// public/my-payments.php
ob_start();

require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/src/db.php';


if (!isset($_SESSION['user_id'])) {
    while (ob_get_level()) ob_end_clean();
    header("Location: login.php");
    exit;
}

require_once '/var/www/mysite/inc/header.php';

$userId = (int)$_SESSION['user_id'];


$monthsRu = [
    1  => 'январь',  2  => 'февраль', 3  => 'март',
    4  => 'апрель',  5  => 'май',      6  => 'июнь',
    7  => 'июль',    8  => 'август',   9  => 'сентябрь',
    10 => 'октябрь', 11 => 'ноябрь',  12 => 'декабрь',
];


function formatDate(string $format, ?string $dateStr): string
{
    if ($dateStr === null || $dateStr === '') {
        return '—';
    }
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return '—';
    }
    return date($format, $ts);
}

/**
 * Возвращает период в формате "мм.ГГГГ"
 */
function formatPeriod(?string $dateStr): string
{
    return formatDate('m.Y', $dateStr);
}


function formatPeriodRu(?string $dateStr, array $monthsRu): string
{
    if ($dateStr === null || $dateStr === '') return '—';
    $ts = strtotime($dateStr);
    if ($ts === false) return '—';
    return $monthsRu[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// --- 1. Получаем общую сумму долга через SUM в БД ---
$debtStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM bills
    WHERE user_id = ? AND status = 'unpaid'
");
$debtStmt->execute([$userId]);
$totalDebt = (float)$debtStmt->fetchColumn();

// --- 2. Получаем детализацию неоплаченных счетов ---
$unpaidStmt = $pdo->prepare("
    SELECT id, period, amount
    FROM bills
    WHERE user_id = ? AND status = 'unpaid'
    ORDER BY period DESC
");
$unpaidStmt->execute([$userId]);
$unpaidBills = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. История оплат с пагинацией ---
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$historyStmt = $pdo->prepare("
    SELECT id, period, amount, paid_at
    FROM bills
    WHERE user_id = ? AND status = 'paid'
    ORDER BY paid_at DESC
    LIMIT ? OFFSET ?
");
$historyStmt->execute([$userId, $perPage, $offset]);
$historyBills = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE user_id = ? AND status = 'paid'");
$countStmt->execute([$userId]);
$totalHistory = (int)$countStmt->fetchColumn();
$totalPages   = (int)ceil($totalHistory / $perPage);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои платежи — ТСЖ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    const TOTAL_DEBT = <?= json_encode($totalDebt) ?>;

    function payStub(amount, billId) {
        const label = billId === 0 ? 'всех счетов' : 'счёта №' + billId;
        if (confirm('Вы будете перенаправлены на шлюз СБП для оплаты ' + label + '.\nСумма: ' + amount.toFixed(2) + ' ₽\n\n(Это симуляция оплаты)')) {
            // TODO: заменить на реальный редирект к ЮКассе / СБП
            alert('Оплата прошла успешно!\n(В реальной версии статус обновится после callback от банка)');
            location.reload();
        }
    }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-4xl mx-auto my-8 space-y-8">

    <!-- БЛОК 1: ТЕКУЩАЯ ЗАДОЛЖЕННОСТЬ -->
    <div class="bg-white p-8 rounded-lg shadow shadow-black/5 border-l-4 <?= $totalDebt > 0 ? 'border-red-500' : 'border-green-500' ?>">
        <h2 class="text-xl font-bold text-slate-900 mb-4">Текущая задолженность</h2>

        <?php if ($totalDebt > 0): ?>
            <div class="flex flex-col md:flex-row justify-between items-center bg-red-50 p-6 rounded-lg">
                <div class="mb-4 md:mb-0">
                    <p class="text-slate-600">Итого к оплате:</p>
                    <p class="text-4xl font-extrabold text-red-600">
                        <?= htmlspecialchars(number_format($totalDebt, 2, '.', ' ')) ?> ₽
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        Включая квитанции за:
                        <?php foreach ($unpaidBills as $b): ?>
                            <?= htmlspecialchars(formatPeriod($b['period'])) ?>;
                        <?php endforeach; ?>
                    </p>
                </div>

                <button onclick="payStub(TOTAL_DEBT, 0)"
                        class="px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-bold rounded-lg shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Оплатить через СБП
                </button>
            </div>

            <!-- Детализация долга -->
            <div class="mt-4">
                <details class="group">
                    <summary class="flex justify-between items-center font-medium cursor-pointer list-none text-blue-600 hover:text-blue-800">
                        <span>Показать детализацию счетов</span>
                        <span class="transition group-open:rotate-180">
                            <svg fill="none" height="24" shape-rendering="geometricPrecision"
                                 stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                 stroke-width="1.5" viewBox="0 0 24 24" width="24">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="text-neutral-600 mt-3 group-open:animate-fadeIn">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-100 text-slate-500">
                                <tr>
                                    <th class="px-4 py-2">Период</th>
                                    <th class="px-4 py-2">Сумма</th>
                                    <th class="px-4 py-2">Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unpaidBills as $bill): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-3">
                                        <?= htmlspecialchars(formatPeriodRu($bill['period'], $monthsRu)) ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold">
                                        <?= htmlspecialchars(number_format((float)$bill['amount'], 2, '.', ' ')) ?> ₽
                                    </td>
                                    <td class="px-4 py-3">
                                        <!-- TODO: заменить заглушку на реальную генерацию PDF -->
                                        <a href="#"
                                           class="text-blue-600 hover:underline"
                                           onclick="alert('Скачивание PDF квитанции...'); return false;">
                                            Скачать PDF
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>

        <?php else: ?>
            <div class="flex items-center gap-4 bg-green-50 p-6 rounded-lg text-green-800">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-bold text-lg">Задолженности нет!</p>
                    <p>Спасибо, что оплачиваете коммунальные услуги вовремя.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- БЛОК 2: ИСТОРИЯ ПЛАТЕЖЕЙ -->
    <div class="bg-white p-8 rounded-lg shadow shadow-black/5">
        <h2 class="text-xl font-bold text-slate-900 mb-6">
            История платежей
            <?php if ($totalHistory > 0): ?>
                <span class="ml-2 text-sm font-normal text-slate-500">(всего: <?= $totalHistory ?>)</span>
            <?php endif; ?>
        </h2>

        <?php if (!empty($historyBills)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-200">
                            <th class="py-3 px-4">Дата оплаты</th>
                            <th class="py-3 px-4">Период</th>
                            <th class="py-3 px-4">Сумма</th>
                            <th class="py-3 px-4">Статус</th>
                            <th class="py-3 px-4">Чек</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700">
                        <?php foreach ($historyBills as $bill): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 px-4">
                                <?= htmlspecialchars(formatDate('d.m.Y H:i', $bill['paid_at'])) ?>
                            </td>
                            <td class="py-3 px-4">
                                <?= htmlspecialchars(formatPeriod($bill['period'])) ?>
                            </td>
                            <td class="py-3 px-4 font-semibold">
                                <?= htmlspecialchars(number_format((float)$bill['amount'], 2, '.', ' ')) ?> ₽
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    Оплачено
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <!-- TODO: заменить заглушку на реальную ссылку на чек -->
                                <a href="#" class="text-slate-400 hover:text-blue-600" title="Скачать чек">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex items-center justify-center gap-2 text-sm">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>"
                       class="px-3 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-50">← Назад</a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>"
                       class="px-3 py-1 rounded border <?= $p === $currentPage ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>"
                       class="px-3 py-1 rounded border border-slate-300 text-slate-600 hover:bg-slate-50">Вперёд →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-slate-500 italic">История платежей пуста.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>