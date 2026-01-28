#!/bin/bash
set -e

DB_FILE="/var/www/mysite/db/users.db"
INIT_SCRIPT="/var/www/mysite/.docker/init_db.php"

if [ ! -f "$DB_FILE" ] || [ ! -s "$DB_FILE" ]; then
    echo "База данных не найдена или пуста."
    if [ -f "$INIT_SCRIPT" ]; then
        echo "Инициализация через $INIT_SCRIPT..."
        php "$INIT_SCRIPT"
    else
        echo "Предупреждение: Файл инициализации $INIT_SCRIPT не найден. Пропускаю..."
    fi
fi


if [ -d "/var/www/mysite/db" ]; then
    chown -R www-data:www-data /var/www/mysite/db
    chmod -R 775 /var/www/mysite/db
fi


if [ $# -gt 0 ]; then
    exec "$@"
else
    exec php-fpm
fi