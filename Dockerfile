FROM php:8.1-fpm

# Устанавливаем зависимости и чистим кэш в одном слое
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    git \
    libfcgi-bin \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer 

WORKDIR /var/www/mysite

# Кэшируем зависимости
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --no-dev

# Копируем код
COPY . .
RUN composer dump-autoload --optimize

# Права на файлы (в проде лучше не давать всё www-data, но для начала ок)
RUN chown -R www-data:www-data /var/www/mysite

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]