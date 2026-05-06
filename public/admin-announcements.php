<?php
// public/admin-announcements.php
ob_start();
require_once '/var/www/mysite/inc/init.php';
require_once '/var/www/mysite/inc/header.php';
require_once '/var/www/mysite/src/db.php';

// Проверка прав админа
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Доступ запрещен. Только для администрации.");
}

$success = '';
$error = '';
$edit_id = null;
$edit_ann = null;

// --- ОБРАБОТКА УДАЛЕНИЯ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности (CSRF).");
    }

    $delete_id = (int)$_POST['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    $success = "delete";
}

// --- ОБРАБОТКА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die("Ошибка безопасности (CSRF).");
    }

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $announcement_id = $_POST['announcement_id'] ?? null;

    if (empty($title)) {
        $error = "Заголовок не может быть пустым.";
    } elseif (empty($body)) {
        $error = "Содержимое объявления не может быть пустым.";
    } else {
        if ($announcement_id) {
            // Редактирование
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET title = ?, body = ?, is_pinned = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$title, $body, $is_pinned, $announcement_id]);
            $success = "edit";
        } else {
            // Создание нового
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, body, is_pinned) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$title, $body, $is_pinned]);
            $success = "create";
        }
    }
}

// --- ПОЛУЧЕНИЕ ОБЪЯВЛЕНИЯ ДЛЯ РЕДАКТИРОВАНИЯ ---
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_ann = $stmt->fetch();
    
    if (!$edit_ann) {
        die("Объявление не найдено.");
    }
}

// --- ПОЛУЧЕНИЕ ВСЕХ ОБЪЯВЛЕНИЙ ---
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
    <title>Управление объявлениями — ТСЖ</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans leading-relaxed">
<?php render_header(); ?>

<div class="w-11/12 max-w-4xl mx-auto my-8 space-y-6">

    <!-- Заголовок -->
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Управление объявлениями</h1>
        <p class="mt-1 text-sm text-slate-500">Добавляйте и редактируйте важную информацию для жильцов</p>
    </div>

    <!-- Уведомления об успехе -->
    <?php if ($success === 'create'): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✓ Объявление успешно создано.
        </div>
    <?php elseif ($success === 'edit'): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✓ Объявление успешно отредактировано.
        </div>
    <?php elseif ($success === 'delete'): ?>
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            ✓ Объявление успешно удалено.
        </div>
    <?php endif; ?>

    <!-- ФОРМА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
            <h2 class="font-semibold text-slate-900">
                <?= $edit_ann ? '✏️ Редактирование объявления' : '➕ Новое объявление' ?>
            </h2>
        </div>
        <div class="px-6 py-5">
            <?php if ($error): ?>
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="save_announcement" value="1">
                <?php if ($edit_ann): ?>
                    <input type="hidden" name="announcement_id" value="<?= $edit_ann['id'] ?>">
                <?php endif; ?>

                <!-- Заголовок -->
                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Заголовок <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        required
                        maxlength="255"
                        value="<?= htmlspecialchars($edit_ann['title'] ?? '') ?>"
                        placeholder="Например: Плановое отключение воды"
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    >
                </div>

                <!-- Содержимое -->
                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Содержимое <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="body"
                        required
                        rows="8"
                        placeholder="Напишите подробную информацию для жильцов. Можно использовать переносы строк."
                        class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                    ><?= htmlspecialchars($edit_ann['body'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">Переносы строк сохраняются</p>
                </div>

                <!-- Закрепить -->
                <div class="flex items-center gap-3 pt-2">
                    <input
                        type="checkbox"
                        id="is_pinned"
                        name="is_pinned"
                        <?= ($edit_ann && $edit_ann['is_pinned']) ? 'checked' : '' ?>
                        class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20"
                    >
                    <label for="is_pinned" class="text-sm font-medium text-slate-700 cursor-pointer">
                        📌 Закрепить объявление (будет отображаться в начале списка)
                    </label>
                </div>

                <!-- Кнопки -->
                <div class="flex gap-2 pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                    >
                        <?= $edit_ann ? '💾 Сохранить изменения' : '➕ Создать объявление' ?>
                    </button>
                    <?php if ($edit_ann): ?>
                        <a href="admin-announcements.php" 
                           class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            ✕ Отменить
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- СПИСОК ОБЪЯВЛЕНИЙ -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
            <h2 class="font-semibold text-slate-900">
                Объявления (<?= count($announcements) ?>)
            </h2>
        </div>
        <div class="divide-y divide-slate-200">
            <?php if (!empty($announcements)): ?>

                <?php foreach ($announcements as $ann): ?>
                    <div class="px-6 py-4 hover:bg-slate-50 transition-colors">
                        <!-- Шапка объявления -->
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <?php if ($ann['is_pinned']): ?>
                                        <span class="inline-block text-amber-600 font-semibold">📌</span>
                                    <?php endif; ?>
                                    <h3 class="font-semibold text-slate-900">
                                        <?= htmlspecialchars($ann['title']) ?>
                                    </h3>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    Создано: <?= date('d.m.Y в H:i', strtotime($ann['created_at'])) ?>
                                    <?php if ($ann['updated_at'] !== $ann['created_at']): ?>
                                        | Отредактировано: <?= date('d.m.Y в H:i', strtotime($ann['updated_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Кнопки действий -->
                            <div class="flex gap-2 ml-4">
                                <a href="?edit=<?= $ann['id'] ?>"
                                   class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                    ✏️ Редактировать
                                </a>
                                <form method="post" style="display: inline;" 
                                      onsubmit="return confirm('Удалить это объявление?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="delete" value="<?= $ann['id'] ?>">
                                    <button type="submit"
                                            class="inline-flex items-center rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors">
                                        🗑️ Удалить
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Превью содержимого -->
                        <div class="mt-3 text-sm text-slate-600 whitespace-pre-line line-clamp-2">
                            <?= htmlspecialchars($ann['body']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="px-6 py-12 text-center text-slate-500">
                    <p class="font-semibold">Объявлений нет</p>
                    <p class="text-xs mt-1">Создайте первое объявление выше</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>