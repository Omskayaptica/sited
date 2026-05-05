FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    git \
    libfcgi-bin \
    curl \
    procps \
    netcat-openbsd \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/mysite

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts \
    --no-autoloader --prefer-dist --no-dev

COPY . .
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/mysite

COPY .docker/init_db.php /init_db.php

# Healthcheck скрипт
COPY docker/healthcheck.sh /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]