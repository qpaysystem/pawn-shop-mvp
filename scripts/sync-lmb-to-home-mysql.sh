#!/usr/bin/env bash
# Синхронизация из 1С (PostgreSQL) в MySQL lombard.home с Mac.
# vm-web-host-01 не видит 192.168.7.250 — читаем 1С с Mac, пишем в домашнюю БД через SSH-туннель.
#
# Использование:
#   ./scripts/sync-lmb-to-home-mysql.sh contragents
#   ./scripts/sync-lmb-to-home-mysql.sh contragents --backfill-passports-from-inforeg --backfill-identity-from-1c
#
# Требуется: Mac в сети с доступом к 192.168.7.250:5432, SSH к lombard-portal.

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOCAL_PORT="${LOCAL_MYSQL_TUNNEL_PORT:-13307}"
ARTISAN_CMD="${1:-}"
shift || true

if [[ -z "$ARTISAN_CMD" ]]; then
  echo "Укажите команду: contragents | purchase | pawn" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MYSQL_IP=$(ssh -q "$DEPLOY_SSH" "docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' lombard-portal-lombard-mysql-1")
DB_PASS=$(ssh -q "$DEPLOY_SSH" "grep '^DB_PASSWORD=' /opt/stacks/prod/lombard-portal/app/.env | cut -d= -f2-")

cleanup() {
  [[ -n "${SSH_PID:-}" ]] && kill "$SSH_PID" 2>/dev/null || true
}
trap cleanup EXIT

ssh -f -N -o ExitOnForwardFailure=yes -L "${LOCAL_PORT}:${MYSQL_IP}:3306" "$DEPLOY_SSH"
SSH_PID=$(pgrep -f "ssh -f -N.*${LOCAL_PORT}:${MYSQL_IP}" | head -1 || true)

export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$LOCAL_PORT" DB_DATABASE=pawn_shop
export DB_USERNAME=pawn DB_PASSWORD="$DB_PASS" DB_SOCKET=

case "$ARTISAN_CMD" in
  contragents)
    php artisan lmb:sync-contragents "$@"
    ;;
  purchase)
    php artisan lmb:sync-purchase-contracts "$@"
    ;;
  stores)
    php artisan lmb:sync-stores-from-1c "$@"
    ;;
  pawn)
    php artisan lmb:sync-pawn-contracts "$@"
    ;;
  *)
    echo "Неизвестная команда: $ARTISAN_CMD" >&2
    exit 1
    ;;
esac
