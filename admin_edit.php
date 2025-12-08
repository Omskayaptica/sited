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

$id = $_GET['id'] ?? null;
if (!$id) die("Не указан ID заявки");

// 2. Обработка сохранения формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
<link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
    <style>
        .edit-box { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .info-row { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        textarea { width: 100%; height: 100px; margin-bottom: 10px; }
        select { width: 100%; padding: 5px; }
        button { padding: 10px 20px; cursor: pointer; background: green; color: white; border: none; }
    </style>
</head>
<body>
<?php render_header(); ?>
<div class="container">
    <h1>Ответ на заявку #<?= htmlspecialchars($req['id']) ?></h1>
    <a href="index.php">← Вернуться назад</a>

    <div class="edit-box">
        <!-- Информация о заявке (не редактируется) -->
        <div class="info-row">
            <strong>Жилец:</strong> <?= htmlspecialchars($req['full_name']) ?> (Кв. <?= htmlspecialchars($req['apartment']) ?>)<br>
            <strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($req['created_at'])) ?><br>
            <strong>Категория:</strong> <?= htmlspecialchars($req['category']) ?>
        </div>
        <div class="info-row">
            <strong>Тема:</strong> <?= htmlspecialchars($req['title']) ?><br>
            <strong>Описание:</strong><br>
            <?= nl2br(htmlspecialchars($req['description'])) ?>
        </div>

        <!-- Форма ответа -->
        <form method="post">
            <label>Статус заявки:</label>
            <select name="status">
                <option value="new" <?= $req['status'] == 'new' ? 'selected' : '' ?>>Новая (New)</option>
                <option value="in_progress" <?= $req['status'] == 'in_progress' ? 'selected' : '' ?>>В работе</option>
                <option value="done" <?= $req['status'] == 'done' ? 'selected' : '' ?>>Выполнена</option>
                <option value="rejected" <?= $req['status'] == 'rejected' ? 'selected' : '' ?>>Отклонена</option>
            </select>

            <label>Ответ ТСЖ (комментарий):</label>
            <textarea name="admin_comment" placeholder="Например: Сантехник подойдет завтра в 10:00"><?= htmlspecialchars($req['admin_comment'] ?? '') ?></textarea>

            <button type="submit">Сохранить ответ</button>
        </form>
    </div>
</div>
</body>
</html>
