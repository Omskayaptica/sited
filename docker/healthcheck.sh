#!/bin/bash
# healthcheck.sh - проверка PHP-FPM здоровья

set -e

# 1. Проверка: процесс запущен?
if ! pgrep -f "php-fpm: master" > /dev/null; then
    echo "✗ PHP-FPM process not running"
    exit 1
fi

# 2. Проверка: сокет слушает?
if ! nc -z 127.0.0.1 9000 2>/dev/null; then
    echo "✗ PHP-FPM socket not responding"
    exit 1
fi

# 3. (Опционально) Проверка реального запроса через curl
# Раскомментируй если нужна полная проверка
# if ! curl -sf http://127.0.0.1:80/ > /dev/null 2>&1; then
#     echo "✗ HTTP request failed"
#     exit 1
# fi

echo "✓ PHP-FPM is healthy"
exit 0