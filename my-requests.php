<?php
// public/my-requests.php
ob_start();
require_once '/var/www/mysite/inc/init.php'; 
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Проверка: вошел ли пользователь
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- ГЕНЕРАЦИЯ CSRF ТОКЕНА ---
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// --- ОБРАБОТКА ФОРМЫ (СОЗДАНИЕ ЗАЯВКИ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    // Проверка CSRF
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности");
    }

    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    if (mb_strlen($title) > 5 && mb_strlen($description) > 5) {
        $stmt = $pdo->prepare("INSERT INTO requests (user_id, category, title, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $category, $title, $description]);
        
        header("Location: my-requests.php?success=1");
        exit;
    }
}

// --- ПОЛУЧЕНИЕ ТОЛЬКО СВОИХ ЗАЯВОК ---
$stmt = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои заявки</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-5xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Мои заявки в ТСЖ</h1>

    <!-- Форма подачи -->
    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-6">
        <h3 class="text-lg font-semibold text-slate-900 text-center">Подать новую заявку</h3>
        <form method="post" class="mt-4 space-y-4">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="create_request" value="1">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700">Категория</label>
                <select name="category" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="Сантехника">Сантехника</option>
                <option value="Электрика">Электрика</option>
                <option value="Уборка">Уборка</option>
                <option value="Другое">Другое</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700">Тема</label>
                <input type="text" name="title" required class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700">Описание</label>
                <textarea name="description" required class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 h-24"></textarea>
            </div>
            
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Отправить</button>
        </form>
    </div>

    <hr class="my-8 border-slate-200">

    <!-- Таблица своих заявок -->
    <div class="mt-2 overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead class="bg-blue-600 text-white">
            <tr>
                <th class="px-4 py-3 text-left font-semibold">Дата</th>
                <th class="px-4 py-3 text-left font-semibold">Категория</th>
                <th class="px-4 py-3 text-left font-semibold">Проблема</th>
                <th class="px-4 py-3 text-left font-semibold">Статус</th>
                <th class="px-4 py-3 text-left font-semibold">Ответ ТСЖ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php foreach ($requests as $req): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($req['category']) ?></td>
                    <td>
                        <div class="px-4 py-3">
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($req['title']) ?></div>
                            <div class="mt-1 text-slate-600"><?= htmlspecialchars($req['description']) ?></div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap font-semibold
                        <?php if (($req['status'] ?? '') === 'new') echo 'text-green-600'; ?>
                        <?php if (($req['status'] ?? '') === 'in_progress') echo 'text-amber-600'; ?>
                        <?php if (($req['status'] ?? '') === 'rejected') echo 'text-red-600'; ?>
                        <?php if (($req['status'] ?? '') === 'done') echo 'text-slate-500 line-through'; ?>
                    "><?= htmlspecialchars($req['status']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($req['admin_comment'] ?? 'Ожидает ответа') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>