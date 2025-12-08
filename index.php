<?php
// public/index.php
ob_start();
session_start();
require_once '/var/www/mysite/src/db.php';

// Если не вошел — отправляем на логин
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role']; // 'resident' или 'admin'
$userName = $_SESSION['full_name'];

// --- ОБРАБОТКА ФОРМЫ (СОЗДАНИЕ ЗАЯВКИ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    if ($title && $description) {
        $stmt = $pdo->prepare("INSERT INTO requests (user_id, category, title, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $category, $title, $description]);
        // Перезагрузим страницу, чтобы увидеть новую заявку
        while (ob_get_level()) ob_end_clean();
        header("Location: index.php");
        exit;
    }
}

// --- ПОЛУЧЕНИЕ СПИСКА ЗАЯВОК ---
if ($role === 'admin') {
    // Админ видит ВСЕ заявки + данные о жильце (JOIN таблицы users)
    $sql = "SELECT r.*, u.full_name, u.apartment 
            FROM requests r 
            JOIN users u ON r.user_id = u.id 
            ORDER BY r.created_at DESC";
    $stmt = $pdo->query($sql);
} else {
    // Жилец видит только СВОИ
    $sql = "SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
}
$requests = $stmt->fetchAll();
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет ТСЖ</title>
<link rel="stylesheet" href="style_new.css?v=<?= time() ?>">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-new { color: green; font-weight: bold; }
        .status-done { color: gray; text-decoration: line-through; }
        .form-box { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .header-info { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
<div class="container">
    
    <div class="header-info">
        <h2>Привет, <?= htmlspecialchars($userName) ?>! (Кв. <?= htmlspecialchars($_SESSION['apartment']) ?>)</h2>
        <a href="logout.php">Выйти</a>
    </div>

    <!-- ФОРМА ПОДАЧИ (Только для жильцов) -->
    <?php if ($role !== 'admin'): ?>
        <div class="form-box">
            <h3>Подать новую жалобу/заявку</h3>
            <form method="post">
                <input type="hidden" name="create_request" value="1">
                
                <label>Категория:</label>
                <select name="category">
                    <option value="Сантехника">Сантехника</option>
                    <option value="Электрика">Электрика</option>
                    <option value="Уборка">Уборка подъезда</option>
                    <option value="Другое">Другое</option>
                </select>
                <br><br>
                
                <label>Тема (кратко):</label><br>
                <input type="text" name="title" required placeholder="Например: Течет труба" style="width: 100%">
                <br><br>
                
                <label>Описание проблемы:</label><br>
                <textarea name="description" required placeholder="Опишите подробно..." style="width: 100%; height: 80px;"></textarea>
                <br><br>
                
                <button type="submit">Отправить заявку</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ТАБЛИЦА ЗАЯВОК -->
    <h3><?= ($role === 'admin') ? 'Все заявки жильцов' : 'Мои заявки' ?></h3>
    
    <?php if (count($requests) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <?php if ($role === 'admin'): ?>
                        <th>Кв.</th>
                        <th>ФИО</th>
                    <?php endif; ?>
                    <th>Категория</th>
                    <th>Суть проблемы</th>
                    <th>Статус</th>
                    <th>Ответ ТСЖ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></td>
                        
                        <?php if ($role === 'admin'): ?>
                            <td><?= htmlspecialchars($req['apartment']) ?></td>
                            <td><?= htmlspecialchars($req['full_name']) ?></td>
                        <?php endif; ?>

                        <td><?= htmlspecialchars($req['category']) ?></td>
                        <td>
                            <b><?= htmlspecialchars($req['title']) ?></b><br>
                            <small><?= htmlspecialchars($req['description']) ?></small>
                        </td>
                        <td class="status-<?= $req['status'] ?>">
                            <?= htmlspecialchars($req['status']) ?>
                        </td>
<td>
    <?php if ($role === 'admin'): ?>
        <div style="margin-bottom: 5px;">
            <?= htmlspecialchars($req['admin_comment'] ?? '') ?>
        </div>
        <a href="admin_edit.php?id=<?= $req['id'] ?>" class="btn-small">
            ✏️ Ответить/Изменить статус
        </a>
    <?php else: ?>
        <!-- Жилец просто видит текст -->
        <?= htmlspecialchars($req['admin_comment'] ?? '—') ?>
    <?php endif; ?>
</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Заявок пока нет.</p>
    <?php endif; ?>

</div>
</body>
</html>
