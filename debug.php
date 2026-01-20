<?php
// debug.php - для включения отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Отладка включена. Теперь ошибки будут отображаться.<br>";
echo "Проверка доступности forgot-password.php: " . file_exists('/var/www/mysite/forgot-password.php') ? 'Да' : 'Нет';
