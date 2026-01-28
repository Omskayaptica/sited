FROM php:8.1-fpm

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_sqlite

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer 

WORKDIR /var/www/mysite

# Копируем файлы проекта (сначала только composer файлы для кэширования слоев)
COPY composer.json composer.lock ./

# Устанавливаем зависимости прямо в образ
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Копируем остальной код
COPY . .

# Завершаем установку composer (генерируем autoload)
RUN composer dump-autoload --optimize

# Настраиваем права
RUN chown -R www-data:www-data /var/www/mysite

# Entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]