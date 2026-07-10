#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="${DEPLOY_CONFIG_FILE:-${PROJECT_ROOT}/.backup.env}"
TARGET_IMAGE="${1:-}"
COMPOSE_CMD=()
COMPOSE_FILES=()
LOCK_DIR="${PROJECT_ROOT}/storage/backup/.deploy-container.lock"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

is_true() {
    case "${1:-}" in
        1|true|TRUE|yes|YES|on|ON) return 0 ;;
        *) return 1 ;;
    esac
}

cleanup() {
    rmdir "$LOCK_DIR" 2>/dev/null || true
}

detect_compose() {
    if docker compose version >/dev/null 2>&1; then
        COMPOSE_CMD=(docker compose)
    elif command -v docker-compose >/dev/null 2>&1; then
        COMPOSE_CMD=(docker-compose)
    else
        fail "Docker Compose was not found."
    fi
}

compose_with_image() {
    local image="$1"
    shift
    XBOARD_IMAGE="$image" "${COMPOSE_CMD[@]}" "${COMPOSE_FILES[@]}" "$@"
}

health_check() {
    local attempt=1
    local curl_args=(-fsS --max-time 5)
    if [ -n "$DEPLOY_HEALTH_HOST" ]; then
        curl_args+=(-H "Host: ${DEPLOY_HEALTH_HOST}")
    fi

    while [ "$attempt" -le "$DEPLOY_HEALTH_ATTEMPTS" ]; do
        if curl "${curl_args[@]}" "$DEPLOY_HEALTH_URL" >/dev/null; then
            log "Health check passed on attempt ${attempt}."
            return 0
        fi
        log "Health check ${attempt}/${DEPLOY_HEALTH_ATTEMPTS} failed; retrying in ${DEPLOY_HEALTH_INTERVAL}s."
        sleep "$DEPLOY_HEALTH_INTERVAL"
        attempt=$((attempt + 1))
    done
    return 1
}

[ -n "$TARGET_IMAGE" ] || fail "Usage: scripts/deploy-container.sh <registry/image:tag>"
[[ "$TARGET_IMAGE" =~ ^[a-z0-9.-]+(:[0-9]+)?/[a-z0-9._/-]+:[A-Za-z0-9_.-]+$ ]] || \
    fail "Invalid container image reference: ${TARGET_IMAGE}"

if [ -f "$CONFIG_FILE" ]; then
    # shellcheck disable=SC1090
    set -a
    . "$CONFIG_FILE"
    set +a
fi

: "${DEPLOY_COMPOSE_FILE:=compose.yaml}"
: "${DEPLOY_COMPOSE_OVERRIDE_FILE:=compose.deploy.yaml}"
: "${DEPLOY_APP_SERVICE:=xboard}"
: "${DEPLOY_HEALTH_URL:=http://127.0.0.1:7001/}"
: "${DEPLOY_HEALTH_HOST:=}"
: "${DEPLOY_HEALTH_ATTEMPTS:=30}"
: "${DEPLOY_HEALTH_INTERVAL:=2}"
: "${DEPLOY_RUN_DATA_BACKUP:=true}"

[[ "$DEPLOY_HEALTH_ATTEMPTS" =~ ^[1-9][0-9]*$ ]] || fail "DEPLOY_HEALTH_ATTEMPTS must be positive."
[[ "$DEPLOY_HEALTH_INTERVAL" =~ ^[1-9][0-9]*$ ]] || fail "DEPLOY_HEALTH_INTERVAL must be positive."
[ -f "${PROJECT_ROOT}/${DEPLOY_COMPOSE_FILE}" ] || fail "Missing ${DEPLOY_COMPOSE_FILE}."
[ -f "${PROJECT_ROOT}/${DEPLOY_COMPOSE_OVERRIDE_FILE}" ] || fail "Missing ${DEPLOY_COMPOSE_OVERRIDE_FILE}."

COMPOSE_FILES=(-f "$DEPLOY_COMPOSE_FILE" -f "$DEPLOY_COMPOSE_OVERRIDE_FILE")
cd "$PROJECT_ROOT"
command -v docker >/dev/null 2>&1 || fail "Docker was not found."
command -v curl >/dev/null 2>&1 || fail "curl was not found."
detect_compose

mkdir -p "$(dirname "$LOCK_DIR")"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    fail "Another container deployment is already running."
fi
trap cleanup EXIT INT TERM

compose_with_image "$TARGET_IMAGE" config >/dev/null
compose_with_image "$TARGET_IMAGE" config --services | grep -qx "$DEPLOY_APP_SERVICE" || \
    fail "Compose service '${DEPLOY_APP_SERVICE}' was not found."

container_id="$(compose_with_image "$TARGET_IMAGE" ps -q "$DEPLOY_APP_SERVICE" 2>/dev/null || true)"
previous_image=""
if [ -n "$container_id" ]; then
    previous_image="$(docker inspect --format '{{.Config.Image}}' "$container_id")"
fi

if is_true "$DEPLOY_RUN_DATA_BACKUP" && [ -x "${PROJECT_ROOT}/scripts/backup-r2.sh" ]; then
    log "Backing up persistent data before deployment."
    BACKUP_CONFIG_FILE="$CONFIG_FILE" "${PROJECT_ROOT}/scripts/backup-r2.sh"
fi

log "Pulling immutable image ${TARGET_IMAGE}."
docker pull "$TARGET_IMAGE"

log "Recreating ${DEPLOY_APP_SERVICE}."
compose_with_image "$TARGET_IMAGE" up -d --no-deps --force-recreate --no-build "$DEPLOY_APP_SERVICE"

if health_check; then
    compose_with_image "$TARGET_IMAGE" ps "$DEPLOY_APP_SERVICE"
    log "Deployment completed successfully: ${TARGET_IMAGE}"
    exit 0
fi

compose_with_image "$TARGET_IMAGE" logs --tail=200 "$DEPLOY_APP_SERVICE" || true
if [ -n "$previous_image" ] && docker image inspect "$previous_image" >/dev/null 2>&1; then
    log "Health check failed; restoring ${previous_image}."
    compose_with_image "$previous_image" up -d --no-deps --force-recreate --no-build "$DEPLOY_APP_SERVICE"
    health_check || fail "Rollback container also failed its health check."
    fail "Deployment failed; the previous image was restored. Database changes were not reverted."
fi

fail "Deployment failed and no previous local image was available for rollback."
