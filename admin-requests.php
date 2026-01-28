<?php
// public/admin-requests.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Проверка прав админа
if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Доступ только для администрации.");
}

// Получаем ВСЕ заявки с именами жильцов
$sql = "SELECT r.*, u.full_name, u.apartment 
        FROM requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC";
$requests = $pdo->query($sql)->fetchAll();
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все заявки (Админ)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>
<div class="w-11/12 max-w-6xl mx-auto my-8 bg-white p-8 rounded-lg shadow shadow-black/5">
    <h1 class="text-2xl font-bold text-slate-900">Журнал всех заявок жильцов</h1>
    
    <div class="mt-6 overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead class="bg-blue-600 text-white">
            <tr>
                <th class="px-4 py-3 text-left font-semibold">Дата</th>
                <th class="px-4 py-3 text-left font-semibold">Кв.</th>
                <th class="px-4 py-3 text-left font-semibold">Жилец</th>
                <th class="px-4 py-3 text-left font-semibold">Категория</th>
                <th class="px-4 py-3 text-left font-semibold">Суть</th>
                <th class="px-4 py-3 text-left font-semibold">Статус</th>
                <th class="px-4 py-3 text-left font-semibold">Действие</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php foreach ($requests as $req): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($req['apartment']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($req['full_name']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($req['category']) ?></td>
                    <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($req['title']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap font-semibold
                        <?php if (($req['status'] ?? '') === 'new') echo 'text-green-600'; ?>
                        <?php if (($req['status'] ?? '') === 'in_progress') echo 'text-amber-600'; ?>
                        <?php if (($req['status'] ?? '') === 'rejected') echo 'text-red-600'; ?>
                        <?php if (($req['status'] ?? '') === 'done') echo 'text-slate-500 line-through'; ?>
                    "><?= htmlspecialchars($req['status']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <!-- Ссылка на файл редактирования, который ты скинул выше -->
                        <a class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-1.5 text-white text-sm font-semibold hover:bg-blue-700" href="admin_edit.php?id=<?= $req['id'] ?>">✏️ Ответить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>