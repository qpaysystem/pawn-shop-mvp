#!/usr/bin/env bash
# Запуск Metro на домашнем сервере (tunnel). Держит сессию в tmux «expo».
#
#   export DEPLOY_SSH="server_ubunta@192.168.1.67"
#   ./scripts/start-expo-on-home.sh

set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
REMOTE_MOBILE="/opt/stacks/prod/lombard-portal/app/mobile-app"

ssh "$DEPLOY_SSH" "bash -s" <<REMOTE
set -euo pipefail
export NVM_DIR="\$HOME/.nvm"
. "\$NVM_DIR/nvm.sh"
export CI=1
export EXPO_NO_TELEMETRY=1
export EXPO_NO_DEPENDENCY_VALIDATION=1
export REACT_NATIVE_PACKAGER_HOSTNAME=192.168.1.67
cd ${REMOTE_MOBILE}
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i 's|http://127.0.0.1:8000|http://192.168.1.67:8000|' .env
fi
pgrep -f 'expo start --host lan' | xargs -r kill 2>/dev/null || true
sleep 2
rm -f ~/expo-metro.log
# -c: новые вкладки/экраны подхватятся после deploy
nohup npx expo start --host lan --port 8082 -c > ~/expo-metro.log 2>&1 &
sleep 8
tail -20 ~/expo-metro.log
ss -tlnp 2>/dev/null | grep 8082 || true
echo ""
echo "Expo Go (iPhone в Tailscale): exp://192.168.1.67:8082"
echo "Лог: ssh ${DEPLOY_SSH} 'tail -f ~/expo-metro.log'"
echo "Стоп: ssh ${DEPLOY_SSH} \"kill \\\$(pgrep -f 'expo start --host lan')\""
REMOTE
