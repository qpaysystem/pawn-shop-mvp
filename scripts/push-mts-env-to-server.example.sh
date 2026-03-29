#!/usr/bin/env bash
# Синхронизация переменных MTS_* с локального .env на сервер по SSH.
#
# 1) cp scripts/push-mts-env-to-server.example.sh scripts/push-mts-env-to-server.sh
# 2) Из корня репозитория (подставьте свой логин@хост):
#      export DEPLOY_SSH="cf89938@vh430.timeweb.ru"
#      export DEPLOY_DIR="~/pawn-shop-mvp"
#      ./scripts/push-mts-env-to-server.sh
#
# На сервере: бэкап .env, удаляются только строки, начинающиеся с MTS_,
# затем дописывается блок из локального .env, затем config:clear.
# Один SSH-сеанс (без scp) — при входе по паролю запрос будет один раз.

DEPLOY_SSH="${DEPLOY_SSH:-ЛОГИН@ХОСТ.timeweb.ru}"
DEPLOY_DIR="${DEPLOY_DIR:-~/pawn-shop-mvp}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
REMOTE_FRAG="/tmp/mts_env_pawn_fragment.txt"

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ "$DEPLOY_SSH" == *"ЛОГИН@"* ]]; then
  echo "Задайте DEPLOY_SSH и DEPLOY_DIR или отредактируйте скрипт."
  exit 1
fi

FRAG="$(mktemp)"
trap 'rm -f "$FRAG"' EXIT

grep -E '^MTS_[A-Za-z0-9_]+=' .env > "$FRAG" || {
  echo "В локальном .env нет MTS_*="
  exit 1
}

echo "Копирую блок MTS на ${DEPLOY_SSH}:${DEPLOY_DIR}/.env (один SSH)…"
DELIM="PWN_MTS_ENV_EOF_$(date +%s)_${RANDOM}_$$"
{
  printf '%s\n' "set -euo pipefail"
  printf '%s\n' "cd ${DEPLOY_DIR} || exit 1"
  printf '%s\n' "FR=${REMOTE_FRAG}"
  printf '%s\n' "cat > \"\$FR\" <<'${DELIM}'"
  cat "$FRAG"
  printf '\n%s\n' "${DELIM}"
  printf '%s\n' "test -f .env || { echo 'Нет .env'; exit 1; }"
  printf '%s\n' "cp .env .env.bak.\$(date +%Y%m%d%H%M%S)"
  printf '%s\n' "grep -v '^MTS_' .env > .env.new"
  printf '%s\n' "cat \"\$FR\" >> .env.new"
  printf '%s\n' "mv .env.new .env"
  printf '%s\n' "rm -f \"\$FR\""
  printf '%s\n' "\"${PHP_BIN}\" artisan config:clear"
} | ssh -q "$DEPLOY_SSH" bash

echo "Готово. Проверка на сервере: ${PHP_BIN} artisan mts:debug-response --days=7"
