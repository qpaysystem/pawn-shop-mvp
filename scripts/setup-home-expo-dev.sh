#!/usr/bin/env bash
# Один раз: Node.js 20 на vm-web-host-01 + npm install в mobile-app.
# Metro на сервере — опционально; для dev обычно достаточно Mac: npm run start:phone.
#
#   export DEPLOY_SSH="server_ubunta@192.168.1.67"
#   ./scripts/sync-mobile-app-to-home.sh
#   ./scripts/setup-home-expo-dev.sh

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
REMOTE_MOBILE="${LOMBARD_STACK_DIR}/app/mobile-app"

echo "=== Node.js 20 через nvm (без sudo) на ${DEPLOY_SSH} ==="
ssh "$DEPLOY_SSH" 'bash -s' <<'REMOTE'
set -euo pipefail
export NVM_DIR="$HOME/.nvm"
if [ ! -s "$NVM_DIR/nvm.sh" ]; then
  curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
fi
# shellcheck disable=SC1090
. "$NVM_DIR/nvm.sh"
if ! node -v 2>/dev/null | grep -qE '^v(20|22)\.'; then
  nvm install 20
  nvm alias default 20
fi
echo "Node: $(node -v) npm: $(npm -v)"
REMOTE

echo "=== npm install + @expo/ngrok в ${REMOTE_MOBILE} ==="
ssh "$DEPLOY_SSH" "bash -s" <<REMOTE
set -euo pipefail
export NVM_DIR="\$HOME/.nvm"
. "\$NVM_DIR/nvm.sh"
cd ${REMOTE_MOBILE}
test -f package.json || { echo "Нет package.json — сначала ./scripts/sync-mobile-app-to-home.sh"; exit 1; }
npm install
npm install @expo/ngrok --no-fund --no-audit
echo ""
echo "Запуск Metro на сервере (tunnel, без Tailscale на хосте):"
echo "  cd ${REMOTE_MOBILE}"
echo "  npm run start:phone"
REMOTE

echo "=== Готово ==="
