#!/bin/bash
BACKUP_DIR="./backups"
DB_FILE="./private/_hidden_db_/database.db"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

cp $DB_FILE "$BACKUP_DIR/database_$TIMESTAMP.db"
find $BACKUP_DIR -name "*.db" -mtime +7 -delete

echo "✓ Backup: $BACKUP_DIR/database_$TIMESTAMP.db"