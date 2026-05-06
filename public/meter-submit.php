<?php
// public/meter-submit.php
ob_start();

require_once '/var/www/mysite/inc/init.php'; 
require_once '/var/www/mysite/src/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['apartment'])) {
    while (ob_get_level()) ob_end_clean();
    header("Location: login.php");
    exit;
}

require_once '/var/www/mysite/inc/header.php';

$userId    = (int)$_SESSION['user_id'];
$apartment = (string)$_SESSION['apartment'];


$current_month = date('Y-m-01');

$bill_period = date('Y-m-01');

$error   = '';
$success = '';


$alreadySubmitted = false;

// Тарифы вынесены к началу файла.
// TODO: перенести в таблицу БД `tariffs` или файл конфига,
// чтобы менять без правки кода.
const TARIFF_COLD  = 45.50;   // руб за м³
const TARIFF_HOT   = 215.00;  // руб за м³
const TARIFF_ELEC  = 5.90;    // руб за кВт·ч
const TARIFF_FIXED = 1200.00; // фиксированный платёж (содержание, вывоз мусора)

// Русские названия месяцев (переиспользуем паттерн из my-payments.php)
$monthsRu = [
    1  => 'январь',  2  => 'февраль', 3  => 'март',
    4  => 'апрель',  5  => 'май',      6  => 'июнь',
    7  => 'июль',    8  => 'август',   9  => 'сентябрь',
    10 => 'октябрь', 11 => 'ноябрь',  12 => 'декабрь',
];


function formatDate(string $format, ?string $dateStr): string
{
    if ($dateStr === null || $dateStr === '') return '—';
    $ts = strtotime($dateStr);
    if ($ts === false) return '—';
    return date($format, $ts);
}

// --- Проверяем, не подавались ли показания за текущий месяц ---
try {
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM meter_readings 
        WHERE user_id = ? AND apartment = ? AND month_year = ?
    ");
    $checkStmt->execute([$userId, $apartment, $current_month]);
    $alreadySubmitted = (int)$checkStmt->fetchColumn() > 0;
} catch (PDOException $e) {
    error_log("Ошибка проверки показаний: " . $e->getMessage());
    $error = "Ошибка проверки данных. Попробуйте позже.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySubmitted && empty($error)) {

    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("Ошибка безопасности. Пожалуйста, обновите страницу.");
    }


    $cold_water  = filter_input(INPUT_POST, 'cold_water',  FILTER_VALIDATE_FLOAT,
                       ['options' => ['min_range' => 0, 'max_range' => 99999]]);
    $hot_water   = filter_input(INPUT_POST, 'hot_water',   FILTER_VALIDATE_FLOAT,
                       ['options' => ['min_range' => 0, 'max_range' => 99999]]);
    $electricity = filter_input(INPUT_POST, 'electricity', FILTER_VALIDATE_FLOAT,
                       ['options' => ['min_range' => 0, 'max_range' => 99999]]);


    if ($cold_water === false || $cold_water === null ||
        $hot_water  === false || $hot_water  === null ||
        $electricity === false || $electricity === null) {
        $error = "Пожалуйста, введите корректные числовые значения (от 0 до 99999).";
    }

    if (empty($error)) {
        try {

            $prevStmt = $pdo->prepare("
                SELECT cold_water, hot_water, electricity
                FROM meter_readings
                WHERE user_id = ? AND apartment = ?
                ORDER BY month_year DESC
                LIMIT 1
            ");
            $prevStmt->execute([$userId, $apartment]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

            // Проверка: текущие показания не могут быть меньше предыдущих
            if ($prev) {
                if ((float)$cold_water  < (float)$prev['cold_water'] ||
                    (float)$hot_water   < (float)$prev['hot_water']  ||
                    (float)$electricity < (float)$prev['electricity']) {
                    $error = "Текущие показания не могут быть меньше предыдущих!";
                }
            }

            if (empty($error)) {
                // Считаем расход. При первой подаче prev нет — расход 0,
                // чтобы не выставлять счёт за всё накопленное
                $diff_cold = $prev ? max(0.0, (float)$cold_water  - (float)$prev['cold_water'])  : 0.0;
                $diff_hot  = $prev ? max(0.0, (float)$hot_water   - (float)$prev['hot_water'])   : 0.0;
                $diff_elec = $prev ? max(0.0, (float)$electricity  - (float)$prev['electricity']) : 0.0;

                $bill_amount = round(
                    ($diff_cold * TARIFF_COLD) +
                    ($diff_hot  * TARIFF_HOT)  +
                    ($diff_elec * TARIFF_ELEC) +
                    TARIFF_FIXED,
                    2
                );

                // Атомарная транзакция: показания + счёт вместе
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO meter_readings
                            (user_id, apartment, cold_water, hot_water, electricity, month_year)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId, $apartment,
                        $cold_water, $hot_water, $electricity,
                        $current_month
                    ]);

                    $billStmt = $pdo->prepare("
                        INSERT INTO bills (user_id, amount, period, status)
                        VALUES (?, ?, ?, 'unpaid')
                    ");
                    $billStmt->execute([$userId, $bill_amount, $bill_period]);

                    $pdo->commit();

                    $alreadySubmitted = true;
                    $success = "Показания приняты! Сформирован счёт на сумму: "
                               . number_format($bill_amount, 2, '.', ' ') . " ₽";

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();

                    if ((string)$e->getCode() === '23000') {
                        $error = "Вы уже передавали показания за этот месяц!";
                        $alreadySubmitted = true;
                    } else {
                        error_log("Ошибка сохранения показаний/счёта: " . $e->getMessage());
                        $error = "Не удалось сохранить данные. Попробуйте позже.";
                        $alreadySubmitted = false;
                    }
                }
            }

        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                $error = "Вы уже передавали показания за этот месяц!";
                $alreadySubmitted = true;
            } else {
                error_log("Ошибка при получении предыдущих показаний: " . $e->getMessage());
                $error = "Ошибка при проверке данных. Попробуйте позже.";
            }
        }
    }
}

// История показаний (последние 12 месяцев)
$history = [];
try {
    $histStmt = $pdo->prepare("
        SELECT cold_water, hot_water, electricity, reading_date, month_year
        FROM meter_readings
        WHERE user_id = ? AND apartment = ?
        ORDER BY month_year DESC, reading_date DESC
        LIMIT 12
    ");
    $histStmt->execute([$userId, $apartment]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка получения истории показаний: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Передача показаний — ТСЖ</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-3xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Передача показаний счётчиков</h1>
    <p class="mt-2 text-slate-700">
        Квартира №: <strong><?= htmlspecialchars($apartment) ?></strong>
    </p>
    <p class="text-slate-700">
        Месяц:
        <strong>
            <?php
            $nowMonth = (int)date('n');
            $nowYear  = (int)date('Y');
            echo htmlspecialchars($monthsRu[$nowMonth] . ' ' . $nowYear);
            ?>
        </strong>
    </p>

    <?php if ($alreadySubmitted && empty($success)): ?>
        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
            <strong>Внимание!</strong> Вы уже передавали показания за этот месяц.
        </div>
    <?php endif; ?>

    <div class="mt-6 max-w-md mx-auto rounded-lg border border-slate-200 bg-white p-6">

        <?php if ($success): ?>
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$alreadySubmitted): ?>
        <form method="post" id="meterForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Холодная вода (ХВС), м³
                </label>
                <input type="number" step="0.001" min="0" max="99999"
                       name="cold_water" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Горячая вода (ГВС), м³
                </label>
                <input type="number" step="0.001" min="0" max="99999"
                       name="hot_water" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Электроэнергия, кВт·ч
                </label>
                <input type="number" step="0.001" min="0" max="99999"
                       name="electricity" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>

            <button type="submit" id="submitBtn"
                    class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700 disabled:bg-slate-300 disabled:text-slate-700">
                Отправить показания
            </button>
        </form>
        <?php else: ?>
            <p class="text-sm text-slate-700">
                Следующая передача показаний будет доступна с 1 числа следующего месяца.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- История показаний -->
<div class="w-11/12 max-w-3xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h2 class="text-xl font-bold text-slate-900 mb-4">История показаний</h2>

    <?php if (!empty($history)): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Месяц</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Дата подачи</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ХВС, м³</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ГВС, м³</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Эл-во, кВт·ч</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200">
                <?php foreach ($history as $row): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                        <?= htmlspecialchars(formatDate('m.Y', $row['month_year'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                        <?= htmlspecialchars(formatDate('d.m.Y H:i', $row['reading_date'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                        <?= htmlspecialchars(number_format((float)$row['cold_water'],  3, '.', ' ')) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                        <?= htmlspecialchars(number_format((float)$row['hot_water'],   3, '.', ' ')) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                        <?= htmlspecialchars(number_format((float)$row['electricity'], 3, '.', ' ')) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-slate-500 italic">История показаний пуста.</p>
    <?php endif; ?>
</div>

<script>
function validateInput(input) {
    const value = input.value;
    if (value.includes('.')) {
        const parts = value.split('.');
        if (parts[1].length > 3) {
            input.value = parts[0] + '.' + parts[1].substring(0, 3);
        }
    }

    const submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) return;

    const inputs = document.querySelectorAll('input[type="number"]');
    const hasNegative = Array.from(inputs).some(i => {
        const v = parseFloat(i.value);
        return !isNaN(v) && v < 0;
    });
    submitBtn.disabled = hasNegative;
}
</script>
</body>
</html>