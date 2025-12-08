<?php
// .docker/init_db.php — инициализация БД со схемой (используется в entrypoint.sh)

$dbPath = '/var/www/mysite/db/users.db';
$schemaPath = '/var/www/mysite/db/schema.sql';

// Удаляем старую БД если существует
if (file_exists($dbPath)) {
    unlink($dbPath);
}

try {
    // Создаём новую БД
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Читаем и выполняем schema.sql
    $sql = file_get_contents($schemaPath);
    $pdo->exec($sql);
    
    echo "✓ БД инициализирована успешно!\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
