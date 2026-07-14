#!/usr/bin/env bash
# Синхронизация MTS_* из локального .env в app/.env на lombard.home (Docker).
#
#   export DEPLOY_SSH="server_ubunta@192.168.1.67"
#   export LOMBARD_STACK_DIR="/opt/stacks/prod/lombard-portal"
#   ./scripts/push-mts-env-to-home-lan.sh
#
# Те же ключи, что на Timeweb (скопируйте из рабочего .env или export-mts-env-for-server.sh).

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
REMOTE_APP_ENV="${LOMBARD_STACK_DIR}/app/.env"
REMOTE_FRAG="/tmp/mts_env_lombard_home.txt"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Нет локального .env в $ROOT" >&2
  exit 1
fi

FRAG="$(mktemp)"
trap 'rm -f "$FRAG"' EXIT

if ! grep -qE '^MTS_[A-Za-z0-9_]+=' .env; then
  echo "В локальном .env нет MTS_*= (настройте как на Timeweb)" >&2
  exit 1
fi

grep -E '^MTS_[A-Za-z0-9_]+=' .env > "$FRAG"

echo "Копирую MTS_* на ${DEPLOY_SSH}:${REMOTE_APP_ENV} …"
DELIM="LMB_MTS_ENV_EOF_$(date +%s)_${RANDOM}_$$"
{
  printf '%s\n' "set -euo pipefail"
  printf '%s\n' "ENV_FILE=${REMOTE_APP_ENV}"
  printf '%s\n' "FR=${REMOTE_FRAG}"
  printf '%s\n' "STACK=${LOMBARD_STACK_DIR}"
  printf '%s\n' "test -f \"\$ENV_FILE\" || { echo 'Нет '\$ENV_FILE; exit 1; }"
  printf '%s\n' "cp \"\$ENV_FILE\" \"\${ENV_FILE}.bak.\$(date +%Y%m%d%H%M%S)\""
  printf '%s\n' "grep -v '^MTS_' \"\$ENV_FILE\" > \"\${ENV_FILE}.new\""
  printf '%s\n' "cat > \"\$FR\" <<'${DELIM}'"
  cat "$FRAG"
  printf '\n%s\n' "${DELIM}"
  printf '%s\n' "cat \"\$FR\" >> \"\${ENV_FILE}.new\""
  printf '%s\n' "mv \"\${ENV_FILE}.new\" \"\$ENV_FILE\""
  printf '%s\n' "rm -f \"\$FR\""
  printf '%s\n' "cd \"\$STACK\" && docker compose exec -T lombard-app php artisan config:clear"
  printf '%s\n' "cd \"\$STACK\" && docker compose exec -T lombard-app php artisan mts:debug-response --days=1"
} | ssh -q "$DEPLOY_SSH" bash

echo "Готово. Проверьте в UI: Колл-центр → «Загрузить звонки с MTS»."
