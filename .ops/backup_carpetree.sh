#!/usr/bin/env bash
set -euo pipefail
DATE=$(date +%F_%H-%M-%S)
BASE_DIR=/home/deploy/backups
DB_BACKUP_DIR=$BASE_DIR/db
UPLOADS_BACKUP_DIR=$BASE_DIR/uploads
LOG_DIR=$BASE_DIR/logs
DB_NAME=carpetree
MYSQLDUMP=$(command -v mysqldump)
TAR=$(command -v tar)
GZIP=$(command -v gzip)
WEB_ROOT=/var/www/carpetree.com
UPLOADS_SRC_1=$WEB_ROOT/server/uploads
UPLOADS_SRC_2=$WEB_ROOT/uploads
mkdir -p "$DB_BACKUP_DIR" "$UPLOADS_BACKUP_DIR" "$LOG_DIR"
echo "[${DATE}] Starting backup"
# DB dump using ~/.my.cnf for credentials
echo "Dumping MySQL database $DB_NAME..."
$MYSQLDUMP --single-transaction --quick --routines --triggers --events "$DB_NAME" | $GZIP -c > "$DB_BACKUP_DIR/${DB_NAME}_$DATE.sql.gz"
echo "Database dump saved to $DB_BACKUP_DIR/${DB_NAME}_$DATE.sql.gz"
# Uploads archive (server/uploads)
if [ -d "$UPLOADS_SRC_1" ]; then
  echo "Archiving server uploads from $UPLOADS_SRC_1..."
  $TAR -C "$UPLOADS_SRC_1" -czf "$UPLOADS_BACKUP_DIR/server_uploads_$DATE.tar.gz" . || true
  echo "Server uploads archive: $UPLOADS_BACKUP_DIR/server_uploads_$DATE.tar.gz"
else
  echo "WARNING: uploads source directory not found: $UPLOADS_SRC_1"
fi
# Uploads archive (/var/www/.../uploads)
if [ -d "$UPLOADS_SRC_2" ]; then
  echo "Archiving root uploads from $UPLOADS_SRC_2..."
  $TAR -C "$UPLOADS_SRC_2" -czf "$UPLOADS_BACKUP_DIR/uploads_$DATE.tar.gz" . || true
  echo "Root uploads archive: $UPLOADS_BACKUP_DIR/uploads_$DATE.tar.gz"
else
  echo "WARNING: uploads source directory not found: $UPLOADS_SRC_2"
fi
# Retention: 14 days
find "$DB_BACKUP_DIR" -type f -name '*.sql.gz' -mtime +14 -print -delete || true
find "$UPLOADS_BACKUP_DIR" -type f -name '*.tar.gz' -mtime +14 -print -delete || true
# List sizes
ls -lh "$DB_BACKUP_DIR" | tail -n +1
ls -lh "$UPLOADS_BACKUP_DIR" | tail -n +1
echo "[${DATE}] Backup complete"
