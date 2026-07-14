#!/usr/bin/env bash
# Пересборка нативного приложения «Ломбард» на подключённый iPhone.
# Исправляет «Профиль приложения … недоступен» (истёкший dev provisioning profile).
#
# Требования: Mac, Xcode, iPhone по USB, доверие разработчику на телефоне.
#
#   ./scripts/set-mobile-env-wan.sh          # LTE / белый IP
#   ./scripts/set-mobile-env-home-lan.sh     # дома по Tailscale → 192.168.1.67
#   ./scripts/rebuild-ios-device.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}/mobile-app"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Создан mobile-app/.env из примера — проверьте EXPO_PUBLIC_API_BASE_URL"
fi

echo "=== API из .env ==="
grep -E '^EXPO_PUBLIC_' .env || true
echo ""
echo "=== Сборка и установка на iPhone (expo run:ios --device) ==="
echo "Если несколько устройств — выберите iPhone в списке."
echo ""

npx expo run:ios --device
