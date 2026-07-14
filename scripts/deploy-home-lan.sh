#!/usr/bin/env bash
# Деплой pawn-shop-mvp на домашний сервер (Docker lombard-portal).
# Использование:
#   ./scripts/deploy-home-lan.sh
# С Mac вне LAN (через белый IP :2222 → ai-core):
#   export DEPLOY_SSH="vm-web-host-01"
# Дома по LAN:
#   export DEPLOY_SSH="server_ubunta@192.168.1.67"

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-vm-web-host-01}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
REMOTE_APP="${LOMBARD_STACK_DIR}/app"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== rsync → ${DEPLOY_SSH}:${REMOTE_APP} ==="
rsync -avz --delete \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'mobile-app/node_modules' \
  --exclude 'vendor' \
  --exclude '.env' \
  --exclude 'storage' \
  --exclude 'mobile-app' \
  "$ROOT/" "${DEPLOY_SSH}:${REMOTE_APP}/"

echo "=== deploy.sh в контейнере lombard-app ==="
ssh "$DEPLOY_SSH" "cd ${LOMBARD_STACK_DIR} && docker compose exec -T lombard-app ./deploy.sh"

echo "=== Готово. Проверьте http://lombard.home и API /api/v1/auth/login ==="
echo "Tailscale IP сервера: ssh ${DEPLOY_SSH} 'tailscale ip -4'"
