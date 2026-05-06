<?php
// public/announcements.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Получаем все объявления (закреплённые сверху, потом новые)
$stmt = $pdo->prepare("
    SELECT * FROM announcements 
    ORDER BY is_pinned DESC, created_at DESC
");
$stmt->execute();
$announcements = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Объявления ТСЖ — ТСЖ «Наш Дом»</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-3xl mx-auto my-8 space-y-6">

    <!-- Заголовок -->
    <div>
        <h1 class="text-2xl font-bold text-slate-900">📢 Объявления ТСЖ</h1>
        <p class="mt-1 text-sm text-slate-500">Важная информация для жильцов</p>
    </div>

    <!-- Список объявлений -->
    <?php if (!empty($announcements)): ?>
        
        <?php foreach ($announcements as $ann): ?>
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5 overflow-hidden
                        <?php if ($ann['is_pinned']): ?>border-l-4 border-l-amber-500<?php endif; ?>">
                
                <!-- Шапка объявления -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">
                                <?php if ($ann['is_pinned']): ?>
                                    <span class="text-amber-600">📌</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($ann['title']) ?>
                            </h2>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            <?= date('d.m.Y в H:i', strtotime($ann['created_at'])) ?>
                        </p>
                    </div>
                </div>

                <!-- Содержимое объявления -->
                <div class="px-6 py-5">
                    <div class="prose prose-sm text-slate-700 whitespace-pre-line">
                        <?= htmlspecialchars($ann['body']) ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <!-- Пустое состояние -->
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5 p-12 text-center">
            <div class="text-5xl mb-4">📭</div>
            <p class="font-semibold text-slate-700">Объявлений пока нет</p>
            <p class="mt-1 text-sm text-slate-500">Администрация ТСЖ скоро добавит важную информацию</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>