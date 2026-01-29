<?php
// public/my-payments.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Получаем текущую задолженность (статус 'unpaid')
$unpaidStmt = $pdo->prepare("SELECT * FROM bills WHERE user_id = ? AND status = 'unpaid' ORDER BY period DESC");
$unpaidStmt->execute([$_SESSION['user_id']]);
$unpaidBills = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);

// Считаем общую сумму
$totalDebt = 0;
foreach ($unpaidBills as $bill) {
    $totalDebt += $bill['amount'];
}

// 2. Получаем историю оплат (статус 'paid')
$historyStmt = $pdo->prepare("SELECT * FROM bills WHERE user_id = ? AND status = 'paid' ORDER BY paid_at DESC");
$historyStmt->execute([$_SESSION['user_id']]);
$historyBills = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои платежи — ТСЖ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Функция-заглушка для оплаты
        function payStub(amount, billId) {
            if(confirm('Вы будете перенаправлены на шлюз СБП для оплаты суммы: ' + amount + ' ₽.\n\n(Это симуляция оплаты)')) {
                // Здесь будет реальный редирект на ЮКассу
                // А пока делаем вид, что оплатили
                alert('Оплата прошла успешно! (В реальной версии статус обновится после callback от банка)');
                
                // Перезагружаем страницу (в реальности статус бы сменился в БД)
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
                    <p class="text-4xl font-extrabold text-red-600"><?= number_format($totalDebt, 2, '.', ' ') ?> ₽</p>
                    <p class="text-sm text-slate-500 mt-1">Включая квитанции за: 
                        <?php foreach($unpaidBills as $b) echo date('m.Y', strtotime($b['period'])) . '; '; ?>
                    </p>
                </div>
                
                <button onclick="payStub(<?= $totalDebt ?>, 0)" 
                        class="px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-bold rounded-lg shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Оплатить через СБП
                </button>
            </div>
            
            <!-- Детализация долга -->
            <div class="mt-4">
                <details class="group">
                    <summary class="flex justify-between items-center font-medium cursor-pointer list-none text-blue-600 hover:text-blue-800">
                        <span> Показать детализацию счетов</span>
                        <span class="transition group-open:rotate-180">
                            <svg fill="none" height="24" shape-rendering="geometricPrecision" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24" width="24"><path d="M6 9l6 6 6-6"></path></svg>
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
                                    <td class="px-4 py-3"><?= date('F Y', strtotime($bill['period'])) ?></td>
                                    <td class="px-4 py-3 font-bold"><?= number_format($bill['amount'], 2, '.', ' ') ?> ₽</td>
                                    <td class="px-4 py-3">
                                        <a href="#" class="text-blue-600 hover:underline" onclick="alert('Скачивание PDF квитанции...')">Скачать PDF</a>
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
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="font-bold text-lg">Задолженности нет!</p>
                    <p>Спасибо, что оплачиваете коммунальные услуги вовремя.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- БЛОК 2: ИСТОРИЯ ПЛАТЕЖЕЙ -->
    <div class="bg-white p-8 rounded-lg shadow shadow-black/5">
        <h2 class="text-xl font-bold text-slate-900 mb-6">История платежей</h2>
        
        <?php if (count($historyBills) > 0): ?>
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
                            <td class="py-3 px-4"><?= date('d.m.Y H:i', strtotime($bill['paid_at'])) ?></td>
                            <td class="py-3 px-4"><?= date('m.Y', strtotime($bill['period'])) ?></td>
                            <td class="py-3 px-4 font-semibold"><?= number_format($bill['amount'], 2, '.', ' ') ?> ₽</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    Оплачено
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <a href="#" class="text-slate-400 hover:text-blue-600" title="Скачать чек">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-slate-500 italic">История платежей пуста.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>