# Dockerfile
FROM php:8.1-fpm

# Устанавливаем драйверы для SQLite и другие полезности
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_sqlite

# Устанавливаем Composer (официальный трюк Docker)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer 

# Указываем рабочую папку внутри контейнера
WORKDIR /var/www/mysite

# Копируем entrypoint скрипт
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Права доступа (важно для SQLite, чтобы www-data мог писать)
RUN chown -R www-data:www-data /var/www/mysite

# Запускаем entrypoint скрипт
ENTRYPOINT ["/entrypoint.sh"]