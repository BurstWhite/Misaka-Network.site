#!/usr/bin/env bash
set -Eeuo pipefail

export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="${PROJECT_ROOT}/.backup.env"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

trim_slashes() {
    local value="$1"
    value="${value#/}"
    value="${value%/}"
    printf '%s' "$value"
}

load_config() {
    [ -f "$CONFIG_FILE" ] || fail "Missing ${CONFIG_FILE}. Copy .backup.env.example to .backup.env and edit it first."
    # shellcheck disable=SC1090
    set -a
    . "$CONFIG_FILE"
    set +a

    : "${RCLONE_REMOTE:=r2}"
    : "${RCLONE_DEST:=}"
    : "${BACKUP_RETENTION_DAYS:=30}"
    : "${BACKUP_LOCAL_DIR:=storage/backup/r2}"
    : "${BACKUP_INCLUDE_LOGS:=false}"
    : "${BACKUP_RCLONE_EXTRA_ARGS:=--s3-no-check-bucket}"

    [ -n "$RCLONE_REMOTE" ] || fail "RCLONE_REMOTE is empty."
    [ -n "$RCLONE_DEST" ] || fail "RCLONE_DEST is empty. Use a bucket path such as your-bucket/backups/Misaka-Network.site."
    [[ "$BACKUP_RETENTION_DAYS" =~ ^[0-9]+$ ]] || fail "BACKUP_RETENTION_DAYS must be a number."

    RCLONE_DEST="$(trim_slashes "$RCLONE_DEST")"
    BACKUP_LOCAL_DIR="${BACKUP_LOCAL_DIR%/}"
}

detect_compose() {
    if docker compose version >/dev/null 2>&1; then
        COMPOSE_CMD=(docker compose)
    elif command -v docker-compose >/dev/null 2>&1; then
        COMPOSE_CMD=(docker-compose)
    else
        fail "Docker Compose was not found. Install Docker Compose v2 or docker-compose."
    fi
}

compose() {
    "${COMPOSE_CMD[@]}" "$@"
}

detect_app_service() {
    local services
    services="$(compose config --services 2>/dev/null)" || fail "Cannot read compose services. Make sure compose.yaml exists and is valid."

    if printf '%s\n' "$services" | grep -qx 'xboard'; then
        APP_SERVICE=xboard
    elif printf '%s\n' "$services" | grep -qx 'web'; then
        APP_SERVICE=web
    else
        fail "Could not find app service. Expected service 'xboard' or 'web'."
    fi

    APP_CONTAINER_ID="$(compose ps -q "$APP_SERVICE")"
    [ -n "$APP_CONTAINER_ID" ] || fail "Service '${APP_SERVICE}' is not running. Start it with docker compose up -d first."
}

prepare_snapshot() {
    TIMESTAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
    LOCAL_BASE="${PROJECT_ROOT}/${BACKUP_LOCAL_DIR}"
    SNAPSHOT_DIR="${LOCAL_BASE}/${TIMESTAMP}"
    ARCHIVE_PATH="${LOCAL_BASE}/${TIMESTAMP}.tar.gz"
    LOCK_DIR="${LOCAL_BASE}/.backup.lock"

    mkdir -p "$LOCAL_BASE"
    if ! mkdir "$LOCK_DIR" 2>/dev/null; then
        fail "Another backup appears to be running: ${LOCK_DIR}"
    fi
    trap 'rm -rf "$LOCK_DIR"' EXIT

    mkdir -p "$SNAPSHOT_DIR/database" "$SNAPSHOT_DIR/redis" "$SNAPSHOT_DIR/files"
}

run_database_backup() {
    log "Creating database backup with php artisan backup:database in service '${APP_SERVICE}'..."
    compose exec -T "$APP_SERVICE" php artisan backup:database

    local container_backup_path
    container_backup_path="$(
        compose exec -T "$APP_SERVICE" sh -lc "ls -t /www/storage/backup/*_database_backup.sql.gz 2>/dev/null | head -n 1" | tr -d '\r'
    )"

    [ -n "$container_backup_path" ] || fail "Database backup command finished, but no backup file was found in /www/storage/backup."

    log "Copying database backup from container: ${container_backup_path}"
    docker cp "${APP_CONTAINER_ID}:${container_backup_path}" "${SNAPSHOT_DIR}/database/"
    compose exec -T "$APP_SERVICE" sh -lc "rm -f '$container_backup_path'" >/dev/null 2>&1 || true
}

copy_if_exists() {
    local src="$1"
    local dest_dir="$2"
    if [ -e "${PROJECT_ROOT}/${src}" ]; then
        mkdir -p "$dest_dir"
        cp -a "${PROJECT_ROOT}/${src}" "$dest_dir/"
        log "Included ${src}"
    else
        log "Skipping missing path: ${src}"
    fi
}

stage_files() {
    log "Staging persistent project files..."
    copy_if_exists ".env" "${SNAPSHOT_DIR}/files"
    copy_if_exists ".docker/.data" "${SNAPSHOT_DIR}/files/.docker"
    copy_if_exists "storage/theme" "${SNAPSHOT_DIR}/files/storage"
    copy_if_exists "plugins" "${SNAPSHOT_DIR}/files"

    if [ "$BACKUP_INCLUDE_LOGS" = "true" ]; then
        copy_if_exists "storage/logs" "${SNAPSHOT_DIR}/files/storage"
    fi
}

backup_redis() {
    local redis_service=""
    local services
    services="$(compose config --services 2>/dev/null || true)"

    if printf '%s\n' "$services" | grep -qx 'redis'; then
        redis_service=redis
    elif [ "$APP_SERVICE" = "xboard" ]; then
        redis_service=xboard
    fi

    [ -n "$redis_service" ] || {
        log "Redis backup skipped: no redis service and app service is not xboard."
        return 0
    }

    local redis_container_id
    redis_container_id="$(compose ps -q "$redis_service" 2>/dev/null || true)"
    [ -n "$redis_container_id" ] || {
        log "Redis backup skipped: service '${redis_service}' is not running."
        return 0
    }

    if docker cp "${redis_container_id}:/data/dump.rdb" "${SNAPSHOT_DIR}/redis/dump.rdb" >/dev/null 2>&1; then
        log "Included Redis dump from service '${redis_service}'."
    else
        log "WARNING: Redis dump was not found at /data/dump.rdb for service '${redis_service}'. Continuing."
    fi
}

write_manifest() {
    {
        printf 'timestamp=%s\n' "$TIMESTAMP"
        printf 'project_root=%s\n' "$PROJECT_ROOT"
        printf 'app_service=%s\n' "$APP_SERVICE"
        printf 'app_container_id=%s\n' "$APP_CONTAINER_ID"
        printf 'rclone_remote=%s\n' "$RCLONE_REMOTE"
        printf 'rclone_dest=%s\n' "$RCLONE_DEST"
        printf 'retention_days=%s\n' "$BACKUP_RETENTION_DAYS"
        printf 'include_logs=%s\n' "$BACKUP_INCLUDE_LOGS"
        printf 'git_commit=%s\n' "$(git -C "$PROJECT_ROOT" rev-parse HEAD 2>/dev/null || true)"
    } > "${SNAPSHOT_DIR}/manifest.txt"
}

create_archive() {
    log "Creating archive: ${ARCHIVE_PATH}"
    tar -C "$LOCAL_BASE" -czf "$ARCHIVE_PATH" "$TIMESTAMP"
}

upload_archive() {
    local remote_path="${RCLONE_REMOTE}:${RCLONE_DEST}/${TIMESTAMP}/"
    local rclone_args=()

    if [ -n "$BACKUP_RCLONE_EXTRA_ARGS" ]; then
        # Simple whitespace splitting is intentional for flag-style options.
        # shellcheck disable=SC2206
        rclone_args=($BACKUP_RCLONE_EXTRA_ARGS)
    fi

    log "Uploading archive to ${remote_path}"
    rclone copy "$ARCHIVE_PATH" "$remote_path" --progress "${rclone_args[@]}"
}

cleanup_old_backups() {
    local rclone_args=()

    if [ -n "$BACKUP_RCLONE_EXTRA_ARGS" ]; then
        # shellcheck disable=SC2206
        rclone_args=($BACKUP_RCLONE_EXTRA_ARGS)
    fi

    log "Cleaning local backups older than ${BACKUP_RETENTION_DAYS} days."
    find "$LOCAL_BASE" -mindepth 1 -maxdepth 1 \( -name '*.tar.gz' -o -type d \) -mtime +"$BACKUP_RETENTION_DAYS" -exec rm -rf {} +

    log "Cleaning remote objects older than ${BACKUP_RETENTION_DAYS} days."
    rclone delete "${RCLONE_REMOTE}:${RCLONE_DEST}" --min-age "${BACKUP_RETENTION_DAYS}d" "${rclone_args[@]}" || \
        log "WARNING: Remote object cleanup failed."
    rclone rmdirs "${RCLONE_REMOTE}:${RCLONE_DEST}" --leave-root "${rclone_args[@]}" || \
        log "WARNING: Remote empty directory cleanup failed."
}

main() {
    cd "$PROJECT_ROOT"
    load_config
    require_command docker
    require_command rclone
    require_command tar
    detect_compose
    detect_app_service
    prepare_snapshot
    run_database_backup
    stage_files
    backup_redis
    write_manifest
    create_archive
    upload_archive
    cleanup_old_backups
    log "Backup completed successfully: ${ARCHIVE_PATH}"
}

main "$@"
