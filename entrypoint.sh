#!/bin/bash
# Инициализация при первом запуске контейнера

if [ ! -d "/var/www/mysite/vendor" ]; then
    echo "Installing Composer dependencies..."
    cd /var/www/mysite
    composer install --no-interaction --prefer-dist
fi

# Инициализируем БД если её нет или пересоздаём
if [ ! -f "/var/www/mysite/db/users.db" ] || [ ! -s "/var/www/mysite/db/users.db" ]; then
    echo "Initializing database..."
    php /var/www/mysite/.docker/init_db.php
fi

# Исправляем права на БД файл (чтобы www-data мог писать)
chmod 666 /var/www/mysite/db/users.db
chown www-data:www-data /var/www/mysite/db/users.db
chmod 777 /var/www/mysite/db

# Запускаем PHP-FPM
exec php-fpm