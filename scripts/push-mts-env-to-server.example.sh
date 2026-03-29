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

echo "Копирую блок MTS на ${DEPLOY_SSH}:${DEPLOY_DIR}/.env …"
scp -q "$FRAG" "${DEPLOY_SSH}:${REMOTE_FRAG}"

ssh -q "$DEPLOY_SSH" "cd ${DEPLOY_DIR} && test -f .env || { echo 'Нет .env'; exit 1; } && cp .env .env.bak.\$(date +%Y%m%d%H%M%S) && grep -v '^MTS_' .env > .env.new && cat ${REMOTE_FRAG} >> .env.new && mv .env.new .env && rm -f ${REMOTE_FRAG} && ${PHP_BIN} artisan config:clear"

echo "Готово. Проверка на сервере: ${PHP_BIN} artisan mts:debug-response --days=7"
