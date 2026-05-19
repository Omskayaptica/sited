#!/bin/bash
set -e

echo "Starting entrypoint.sh..."

DB_DIR="/var/www/private/_hidden_db_"
DB_FILE="$DB_DIR/database.db"

echo "Checking database directory..."
if [ ! -d "$DB_DIR" ]; then
    echo "Creating database directory..."
    mkdir -p "$DB_DIR"
fi

echo "Setting permissions..."
chown -R www-data:www-data "$DB_DIR"
chmod -R 775 "$DB_DIR"

echo "Checking database file..."
if [ ! -s "$DB_FILE" ]; then
    echo "Database file not found or empty, initializing..."
    if [ -f "/init_db.php" ]; then
        echo "Running init_db.php..."
        php "/init_db.php"
        chown www-data:www-data "$DB_FILE"
        echo "Database initialized successfully"
    else
        echo "ERROR: /init_db.php not found!"
        exit 1
    fi
else
    echo "Database file exists, skipping initialization"
fi

echo "Starting PHP-FPM..."
# Graceful shutdown
trap 'echo "Graceful shutdown..."; kill -TERM $PID; wait $PID' SIGTERM SIGINT

php-fpm &
PID=$!

echo "PHP-FPM started with PID $PID"
wait $PID
exit $?
