#!/bin/bash
set -e

DB_DIR="/var/www/mysite/db"
DB_FILE="$DB_DIR/users.db"

# Создаем папку БД если её нет (важно при первом запуске)
if [ ! -d "$DB_DIR" ]; then
    mkdir -p "$DB_DIR"
fi

# Проверяем права на папку с базой
chown -R www-data:www-data "$DB_DIR"
chmod -R 775 "$DB_DIR"

# Если базы нет — инициируем (твой скрипт)
if [ ! -s "$DB_FILE" ]; then
    if [ -f "/var/www/mysite/.docker/init_db.php" ]; then
        php "/var/www/mysite/.docker/init_db.php"
        chown www-data:www-data "$DB_FILE"
    fi
fi

exec php-fpm