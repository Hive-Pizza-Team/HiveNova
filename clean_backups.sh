#!/bin/bash

# Configuration
BACKUP_DIR="includes/backups"
BACKUP_PATTERN="2MoonsBackup_*.sql"
RETENTION_DAYS=7
LOG_FILE="includes/backups/backup_cleanup.log"

# Ensure the backup directory exists and is readable
if [ ! -d "$BACKUP_DIR" ] || [ ! -r "$BACKUP_DIR" ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Backup directory does not exist or is not readable: $BACKUP_DIR" >>"$LOG_FILE"
  exit 1
fi

# Delete all backups older than the retention period
mapfile -t outdated_backups < <(find "$BACKUP_DIR" -type f -name "$BACKUP_PATTERN" -mtime +"$RETENTION_DAYS" -print)

if ((${#outdated_backups[@]} > 0)); then
  # Remove all outdated backups in one go
  find "$BACKUP_DIR" -type f -name "$BACKUP_PATTERN" -mtime +"$RETENTION_DAYS" -exec rm {} +
  echo "$(date '+%Y-%m-%d %H:%M:%S') - INFO: Deleted ${#outdated_backups[@]} outdated backup(s)." >>"$LOG_FILE"
fi
