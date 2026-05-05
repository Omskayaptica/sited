<?php
// .docker/init_db.php — инициализация БД со схемой (используется в entrypoint.sh)

$dbPath = '/var/www/private/_hidden_db_/database.db';
$schemaPath = '/var/www/private/_hidden_db_/schema.sql';

// Проверяем есть ли ДБ и не пустой ли он (чтобы не перезаписывать существующую)
if (file_exists($dbPath) && filesize($dbPath) > 0) {
    echo "✓ Database already exists, skipping\n";
    exit(0);
}

try {
    // Проверяем наличие файла схемы
    if (!file_exists($schemaPath)) {
        echo "✗ Schema file not found: $schemaPath\n";
        exit(1);
    }
    
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
