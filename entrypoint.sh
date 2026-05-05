#!/bin/bash
set -e

DB_DIR="/var/www/private/_hidden_db_"
DB_FILE="$DB_DIR/database.db"

if [ ! -d "$DB_DIR" ]; then
    mkdir -p "$DB_DIR"
fi

chown -R www-data:www-data "$DB_DIR"
chmod -R 775 "$DB_DIR"

if [ ! -s "$DB_FILE" ]; then
    if [ -f "/init_db.php" ]; then
        php "/init_db.php"
        chown www-data:www-data "$DB_FILE"
    fi
fi

# Graceful shutdown
trap 'echo "Graceful shutdown..."; kill -TERM $PID; wait $PID' SIGTERM SIGINT

php-fpm &
PID=$!

wait $PID
exit $?