#!/usr/bin/env bash
# Синхронизация LMB_* (и при необходимости LMB_USER_API_*) из локального .env в app/.env на lombard.home.

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
REMOTE_APP_ENV="${LOMBARD_STACK_DIR}/app/.env"
REMOTE_FRAG="/tmp/lmb_env_lombard_home.txt"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Нет локального .env" >&2
  exit 1
fi

FRAG="$(mktemp)"
trap 'rm -f "$FRAG"' EXIT

grep -E '^(LMB_|LMB_USER_API_)' .env > "$FRAG" || {
  echo "В .env нет LMB_* / LMB_USER_API_*" >&2
  exit 1
}

echo "Копирую LMB_* на ${DEPLOY_SSH}:${REMOTE_APP_ENV} …"
DELIM="LMB_ENV_PUSH_EOF_$(date +%s)_${RANDOM}_$$"
{
  printf '%s\n' "set -euo pipefail"
  printf '%s\n' "ENV_FILE=${REMOTE_APP_ENV}"
  printf '%s\n' "FR=${REMOTE_FRAG}"
  printf '%s\n' "STACK=${LOMBARD_STACK_DIR}"
  printf '%s\n' "test -f \"\$ENV_FILE\" || exit 1"
  printf '%s\n' "cp \"\$ENV_FILE\" \"\${ENV_FILE}.bak.\$(date +%Y%m%d%H%M%S)\""
  printf '%s\n' "grep -vE '^(LMB_|LMB_USER_API_)' \"\$ENV_FILE\" > \"\${ENV_FILE}.new\""
  printf '%s\n' "cat > \"\$FR\" <<'${DELIM}'"
  cat "$FRAG"
  printf '\n%s\n' "${DELIM}"
  printf '%s\n' "cat \"\$FR\" >> \"\${ENV_FILE}.new\""
  printf '%s\n' "mv \"\${ENV_FILE}.new\" \"\$ENV_FILE\""
  printf '%s\n' "rm -f \"\$FR\""
  printf '%s\n' "cd \"\$STACK\" && docker compose exec -T lombard-app php artisan config:clear"
} | ssh -q "$DEPLOY_SSH" bash

echo "Готово."
