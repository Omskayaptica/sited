#!/bin/bash
set -e

# 1. Инициализируем БД, если файла базы нет или он пустой
# Используем путь /var/www/mysite/db/users.db как в вашем скрипте
DB_FILE="/var/www/mysite/db/users.db"

if [ ! -f "$DB_FILE" ] || [ ! -s "$DB_FILE" ]; then
    echo "База данных не найдена. Инициализация через init_db.php..."
    php /var/www/mysite/.docker/init_db.php
fi

# 2. Исправляем права (SQLite критично, чтобы папка и файл были доступны на запись)
echo "Setting permissions for SQLite..."
chown -R www-data:www-data /var/www/mysite/db
chmod 775 /var/www/mysite/db
if [ -f "$DB_FILE" ]; then
    chmod 664 "$DB_FILE"
fi

# 3. Запускаем PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm