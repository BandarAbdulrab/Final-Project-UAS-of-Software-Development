#!/usr/bin/env bash
# scripts/backup_db.sh - Automated Daily Database & Weekly Uploads Backup (SRS-NF09, BR-27, TC-D24)
# Cron schedule (daily at 23:30 WIB):
# 30 23 * * * /bin/bash /path/to/public_html/scripts/backup_db.sh >> /path/to/private/logs/backup.log 2>&1

set -euo pipefail

export TZ="Asia/Jakarta"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_HTML_DIR="$(dirname "$SCRIPT_DIR")"
PRIVATE_DIR="$(dirname "$PUBLIC_HTML_DIR")/private"
BACKUP_DIR="$PRIVATE_DIR/backups"
DEFAULTS_FILE="$PRIVATE_DIR/.my.cnf"
UPLOADS_DIR="$PUBLIC_HTML_DIR/uploads"

DATE_STAMP="$(date +'%Y%m%d')"
DAY_OF_WEEK="$(date +'%u')" # 1 (Monday) to 7 (Sunday)
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

if [[ ! -f "$DEFAULTS_FILE" ]]; then
    echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] ERROR: Missing credentials file at $DEFAULTS_FILE" >&2
    exit 1
fi

# Extract database name from private/config.local.php or .my.cnf
DB_NAME="$(grep -E '^[[:space:]]*database[[:space:]]*=' "$DEFAULTS_FILE" | head -n1 | cut -d'=' -f2 | tr -d '[:space:]"'\''')"

if [[ -z "$DB_NAME" ]]; then
    echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] ERROR: 'database=' key not found in $DEFAULTS_FILE" >&2
    exit 1
fi

DB_DUMP_PATH="$BACKUP_DIR/kedai-${DATE_STAMP}.sql.gz"

echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] Starting backup for database: $DB_NAME"

mysqldump \
    --defaults-extra-file="$DEFAULTS_FILE" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$DB_DUMP_PATH"

chmod 600 "$DB_DUMP_PATH"
echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] Database backup saved: $DB_DUMP_PATH"

# Weekly backup of uploads/ directory every Sunday (day 7)
if [[ "$DAY_OF_WEEK" -eq 7 && -d "$UPLOADS_DIR" ]]; then
    UPLOADS_ARCHIVE="$BACKUP_DIR/uploads-${DATE_STAMP}.tar.gz"
    tar -czf "$UPLOADS_ARCHIVE" -C "$PUBLIC_HTML_DIR" uploads
    chmod 600 "$UPLOADS_ARCHIVE"
    echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] Weekly uploads archive saved: $UPLOADS_ARCHIVE"
fi

# Delete backups older than 30 days
find "$BACKUP_DIR" -type f \( -name "kedai-*.sql.gz" -o -name "uploads-*.tar.gz" \) -mtime +"$RETENTION_DAYS" -delete
echo "[$(date +'%Y-%m-%d %H:%M:%S WIB')] Pruned backup archives older than $RETENTION_DAYS days."