<?php
// public/admin_edit.php
ob_start();
require_once '/var/www/mysite/inc/init.php'; // Тут старт сессии
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// 1. Проверка прав (Только админ!)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Доступ запрещен. Только для председателя.");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) die("Не указан ID заявки или передано неверное значение");

// 2. Обработка сохранения формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    $csrf_post = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf_post)) {
        die("Ошибка безопасности (CSRF). Обновите страницу.");
    }
    
    $status = $_POST['status'];
    $comment = trim($_POST['admin_comment']);
    
    $stmt = $pdo->prepare("UPDATE requests SET status = ?, admin_comment = ? WHERE id = ?");
    $stmt->execute([$status, $comment, $id]);
    
    while (ob_get_level()) ob_end_clean();
    header("Location: index.php"); // Возврат на главную
    exit;
}

// 3. Получаем данные заявки, чтобы показать их в форме
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.apartment 
    FROM requests r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) die("Заявка не найдена");
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование заявки №<?= $id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-5xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Ответ на заявку #<?= htmlspecialchars($req['id']) ?></h1>
    <a class="mt-2 inline-block text-blue-600 hover:underline" href="index.php">← Вернуться назад</a>

    <div class="mt-6 max-w-2xl mx-auto rounded-lg border border-slate-200 bg-white p-6">
        <!-- Информация о заявке (не редактируется) -->
        <div class="pb-4 mb-4 border-b border-slate-200 text-sm text-slate-700 space-y-1">
            <div><strong class="text-slate-900">Жилец:</strong> <?= htmlspecialchars($req['full_name']) ?> (Кв. <?= htmlspecialchars($req['apartment']) ?>)</div>
            <div><strong class="text-slate-900">Дата:</strong> <?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></div>
            <div><strong class="text-slate-900">Категория:</strong> <?= htmlspecialchars($req['category']) ?></div>
        </div>
        <div class="pb-4 mb-4 border-b border-slate-200 text-sm text-slate-700 space-y-2">
            <div><strong class="text-slate-900">Тема:</strong> <?= htmlspecialchars($req['title']) ?></div>
            <div><strong class="text-slate-900">Описание:</strong></div>
            <div class="whitespace-pre-line"><?= htmlspecialchars($req['description']) ?></div>
        </div>

        <!-- Форма ответа -->
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700">Статус заявки</label>
                <select name="status" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="new" <?= $req['status'] == 'new' ? 'selected' : '' ?>>Новая (New)</option>
                <option value="in_progress" <?= $req['status'] == 'in_progress' ? 'selected' : '' ?>>В работе</option>
                <option value="done" <?= $req['status'] == 'done' ? 'selected' : '' ?>>Выполнена</option>
                <option value="rejected" <?= $req['status'] == 'rejected' ? 'selected' : '' ?>>Отклонена</option>
            </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Ответ ТСЖ (комментарий)</label>
                <textarea name="admin_comment" placeholder="Например: Сантехник подойдет завтра в 10:00" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 h-28"><?= htmlspecialchars($req['admin_comment'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700">Сохранить ответ</button>
        </form>
    </div>
</div>
</body>
</html>
