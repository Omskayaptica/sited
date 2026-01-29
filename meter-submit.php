<?php
// public/meter-submit.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Проверка авторизации БОЛЕЕ СТРОГАЯ
if (!isset($_SESSION['user_id']) || !isset($_SESSION['apartment'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';
$current_month = date('Y-m-01'); // Первое число текущего месяца для проверки

// Проверяем, не отправлял ли пользователь показания за этот месяц
try {
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM meter_readings 
        WHERE user_id = ? 
        AND apartment = ? 
        AND month_year = ?
    ");
    $checkStmt->execute([
        $_SESSION['user_id'],
        $_SESSION['apartment'],
        $current_month
    ]);
    $alreadySubmitted = $checkStmt->fetch()['count'] > 0;
} catch (PDOException $e) {
    $error = "Ошибка проверки данных: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySubmitted) {
    // CSRF защита - ОБЯЗАТЕЛЬНО!
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности. Пожалуйста, обновите страницу.");
    }

    $cold_water = filter_input(INPUT_POST, 'cold_water', FILTER_VALIDATE_FLOAT, 
        ['options' => ['min_range' => 0, 'max_range' => 99999]]);
    $hot_water = filter_input(INPUT_POST, 'hot_water', FILTER_VALIDATE_FLOAT,
        ['options' => ['min_range' => 0, 'max_range' => 99999]]);
    $electricity = filter_input(INPUT_POST, 'electricity', FILTER_VALIDATE_FLOAT,
        ['options' => ['min_range' => 0, 'max_range' => 99999]]);

    if ($cold_water === false || $hot_water === false || $electricity === false) {
        $error = "Пожалуйста, введите корректные числовые значения (от 0 до 99999).";
    } elseif ($cold_water < 0 || $hot_water < 0 || $electricity < 0) {
        $error = "Показания не могут быть отрицательными!";
    } else {
        try {
            // Получаем предыдущие показания для проверки роста
            $prevStmt = $pdo->prepare("
                SELECT cold_water, hot_water, electricity 
                FROM meter_readings 
                WHERE user_id = ? AND apartment = ?
                ORDER BY reading_date DESC 
                LIMIT 1
            ");
            $prevStmt->execute([$_SESSION['user_id'], $_SESSION['apartment']]);
            $prev = $prevStmt->fetch();
            
            // Проверка: текущие показания должны быть >= предыдущих
            if ($prev) {
                if ($cold_water < $prev['cold_water'] || 
                    $hot_water < $prev['hot_water'] || 
                    $electricity < $prev['electricity']) {
                    $error = "Текущие показания не могут быть меньше предыдущих!";
                }
            }

            if (empty($error)) {
                // Делаем операцию атомарной: показания + счет.
                // Если создание счета упадёт, откатываем вставку показаний и НЕ показываем успех.
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO meter_readings 
                        (user_id, apartment, cold_water, hot_water, electricity, month_year) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $_SESSION['apartment'],
                        $cold_water,
                        $hot_water,
                        $electricity,
                        $current_month
                    ]);

                $tariff_cold = 45.50;  // руб за м³
                $tariff_hot  = 215.00; // руб за м³
                $tariff_elec = 5.90;   // руб за кВт·ч
                $fix_price   = 1200.00; // Фиксированный платеж (содержание жилья, вывоз мусора)

                // 2. Считаем расход (Текущее - Предыдущее)
                // $prev мы получили выше для валидации. Если $prev нет, значит это первая подача — считаем расход 0 (иначе выставим счет за все годы)
                $diff_cold = ($prev) ? ($cold_water - $prev['cold_water']) : 0;
                $diff_hot  = ($prev) ? ($hot_water - $prev['hot_water']) : 0;
                $diff_elec = ($prev) ? ($electricity - $prev['electricity']) : 0;

                // Защита от глюков: если вдруг разница отрицательная (хотя мы проверяли), ставим 0
                $diff_cold = max(0, $diff_cold);
                $diff_hot  = max(0, $diff_hot);
                $diff_elec = max(0, $diff_elec);

                // 3. Считаем итоговую сумму
                $bill_amount = ($diff_cold * $tariff_cold) +
                               ($diff_hot  * $tariff_hot) +
                               ($diff_elec * $tariff_elec) +
                               $fix_price;

                $bill_amount = round($bill_amount, 2); // Округляем до копеек

                // 4. Создаем счет в таблице bills
                // Формируем период в формате '2025-01'
                $bill_period = date('Y-m');

                    $billStmt = $pdo->prepare("
                        INSERT INTO bills (user_id, amount, period, status) 
                        VALUES (?, ?, ?, 'unpaid')
                    ");
                    $billStmt->execute([$_SESSION['user_id'], $bill_amount, $bill_period]);

                // --- КОНЕЦ БЛОКА АВТОМАТИЧЕСКОГО РАСЧЕТА ---

                    // Операция полностью успешна: и показания сохранены, и счет создан
                    $success = "Показания приняты! Сформирован счет на сумму: " . $bill_amount . " ₽";

                    $pdo->commit();
                    $alreadySubmitted = true; // Блокируем повторную отправку
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Внутри транзакции сюда попадают ВСЕ ошибки (в т.ч. дубликат показаний)
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = "Вы уже передавали показания за этот месяц!";
                        $success = '';
                        $alreadySubmitted = true;
                    } else {
                        error_log("Ошибка при сохранении показаний/создании счета: " . $e->getMessage());
                        $error = "Не удалось сформировать счет. Показания не сохранены. Попробуйте позже.";
                        $success = '';
                        $alreadySubmitted = false;
                    }
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $error = "Вы уже передавали показания за этот месяц!";
            } else {
                $error = "Ошибка при сохранении данных: " . $e->getMessage();
            }
        }
    }
}

// Генерируем CSRF токен если его нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Передача показаний</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-3xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Передача показаний счетчиков</h1>
    <p class="mt-2 text-slate-700">Квартира №: <strong><?= htmlspecialchars($_SESSION['apartment']) ?></strong></p>
    <p class="text-slate-700">Месяц: <strong><?= date('F Y') ?></strong></p>

    <?php if ($alreadySubmitted): ?>
        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
            <strong>Внимание!</strong> Вы уже передавали показания за этот месяц.
        </div>
    <?php endif; ?>

    <div class="mt-6 max-w-md mx-auto rounded-lg border border-slate-200 bg-white p-6">
        <?php if ($success): ?><div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= $error ?></div><?php endif; ?>

        <?php if (!$alreadySubmitted): ?>
        <form method="post" id="meterForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700">Холодная вода (ХВС), м³</label>
                <input type="number" step="0.001" min="0" max="99999" 
                       name="cold_water" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700">Горячая вода (ГВС), м³</label>
                <input type="number" step="0.001" min="0" max="99999" 
                       name="hot_water" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700">Электроэнергия, кВт·ч</label>
                <input type="number" step="0.001" min="0" max="99999" 
                       name="electricity" required placeholder="0.000"
                       oninput="validateInput(this)"
                       class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700 disabled:bg-slate-300 disabled:text-slate-700" id="submitBtn">
                Отправить показания
            </button>
        </form>
        <?php else: ?>
            <p class="text-sm text-slate-700">Следующая передача показаний будет доступна с 1 числа следующего месяца.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function validateInput(input) {
    // Клиентская валидация: не более 3 знаков после запятой
    let value = input.value;
    if (value.includes('.')) {
        let parts = value.split('.');
        if (parts[1].length > 3) {
            input.value = parts[0] + '.' + parts[1].substring(0, 3);
        }
    }
    
    // Блокируем отправку если есть отрицательные значения
    let submitBtn = document.getElementById('submitBtn');
    let inputs = document.querySelectorAll('input[type="number"]');
    let hasNegative = Array.from(inputs).some(i => parseFloat(i.value) < 0);
    submitBtn.disabled = hasNegative;
}
</script>
</body>
</html>