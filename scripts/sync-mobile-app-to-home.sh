#!/usr/bin/env bash
# Копирует mobile-app на домашний сервер (deploy-home-lan.sh mobile-app не отправляет).
#
#   export DEPLOY_SSH="server_ubunta@192.168.1.67"
#   ./scripts/sync-mobile-app-to-home.sh

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
REMOTE_APP="${LOMBARD_STACK_DIR}/app"
REMOTE_MOBILE="${REMOTE_APP}/mobile-app"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "=== rsync mobile-app → ${DEPLOY_SSH}:${REMOTE_MOBILE} ==="
rsync -avz \
  --exclude 'node_modules' \
  --exclude '.expo' \
  --exclude 'dist' \
  --exclude '.env' \
  "${ROOT}/mobile-app/" "${DEPLOY_SSH}:${REMOTE_MOBILE}/"

echo "=== Готово. На сервере: cd ${REMOTE_MOBILE} && npm install ==="
echo "Node на хосте: ./scripts/setup-home-expo-dev.sh (один раз)"
