#!/usr/bin/env bash
set -Eeuo pipefail

export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_SCRIPT="${PROJECT_ROOT}/scripts/backup-r2.sh"
LOG_FILE="${PROJECT_ROOT}/storage/logs/r2-backup.log"
CRON_TIME="${R2_BACKUP_CRON_TIME:-30 3 * * *}"
CRON_MARKER="# Misaka-Network.site R2 backup"
CRON_ENTRY="${CRON_TIME} ${BACKUP_SCRIPT} >> ${LOG_FILE} 2>&1 ${CRON_MARKER}"

if [ ! -x "$BACKUP_SCRIPT" ]; then
    echo "Backup script is not executable: ${BACKUP_SCRIPT}" >&2
    echo "Run: chmod +x ${BACKUP_SCRIPT}" >&2
    exit 1
fi

mkdir -p "$(dirname "$LOG_FILE")"
touch "$LOG_FILE"

current_cron="$(crontab -l 2>/dev/null || true)"
filtered_cron="$(printf '%s\n' "$current_cron" | grep -vF "$CRON_MARKER" || true)"

{
    printf '%s\n' "$filtered_cron" | sed '/^[[:space:]]*$/d'
    printf '%s\n' "$CRON_ENTRY"
} | crontab -

echo "Installed cron entry:"
echo "$CRON_ENTRY"
echo
echo "Log file:"
echo "$LOG_FILE"
