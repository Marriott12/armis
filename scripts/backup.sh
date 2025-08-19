#!/bin/bash

# ARMIS Automated Backup Script
# Creates full database backups with encryption and rotation

BACKUP_DIR="/var/www/html/backups"
LOG_FILE="/var/www/html/shared/logs/backup.log"
RETENTION_DAYS=30
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-armis_production}"
DB_USER="${DB_USER:-armis_user}"
DB_PASS="${DB_PASS:-secure_db_password}"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate backup filename with timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="armis_backup_$TIMESTAMP.sql"
ENCRYPTED_BACKUP="${BACKUP_FILE}.enc"

echo "$(date): Starting automated backup..." >> "$LOG_FILE"

# Create database backup
mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --add-drop-database \
    --databases "$DB_NAME" > "$BACKUP_DIR/$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "$(date): Database backup created successfully: $BACKUP_FILE" >> "$LOG_FILE"
    
    # Encrypt backup file
    openssl enc -aes-256-cbc -salt -in "$BACKUP_DIR/$BACKUP_FILE" -out "$BACKUP_DIR/$ENCRYPTED_BACKUP" -k "$(cat /var/www/html/shared/keys/backup.key 2>/dev/null || echo 'default_backup_key')"
    
    if [ $? -eq 0 ]; then
        echo "$(date): Backup encrypted successfully: $ENCRYPTED_BACKUP" >> "$LOG_FILE"
        rm "$BACKUP_DIR/$BACKUP_FILE"  # Remove unencrypted backup
        
        # Compress encrypted backup
        gzip "$BACKUP_DIR/$ENCRYPTED_BACKUP"
        echo "$(date): Backup compressed: ${ENCRYPTED_BACKUP}.gz" >> "$LOG_FILE"
        
        # Update backup log in database
        php -r "
        require_once '/var/www/html/shared/database_connection.php';
        try {
            \$pdo = getDbConnection();
            \$stmt = \$pdo->prepare('INSERT INTO backup_log (backup_type, status, file_path, file_size, started_at, completed_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            \$fileSize = filesize('$BACKUP_DIR/${ENCRYPTED_BACKUP}.gz');
            \$stmt->execute(['full', 'completed', '${ENCRYPTED_BACKUP}.gz', \$fileSize]);
            echo '$(date): Backup logged to database\n';
        } catch (Exception \$e) {
            error_log('Backup logging failed: ' . \$e->getMessage());
        }
        " >> "$LOG_FILE"
        
    else
        echo "$(date): ERROR: Backup encryption failed" >> "$LOG_FILE"
        rm "$BACKUP_DIR/$BACKUP_FILE"
    fi
else
    echo "$(date): ERROR: Database backup failed" >> "$LOG_FILE"
fi

# Clean up old backups
find "$BACKUP_DIR" -name "armis_backup_*.sql.enc.gz" -mtime +$RETENTION_DAYS -delete
echo "$(date): Old backups cleaned up (retention: $RETENTION_DAYS days)" >> "$LOG_FILE"

# Create system health snapshot
echo "$(date): Creating system health snapshot..." >> "$LOG_FILE"
curl -s "http://localhost/api/v1/health" > "$BACKUP_DIR/health_$TIMESTAMP.json"

echo "$(date): Automated backup completed" >> "$LOG_FILE"