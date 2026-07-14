#!/usr/bin/env bash
# API через Tailscale subnet → vm-web-host-01 (192.168.1.67:8000).
# iPhone: Tailscale Connected, маршрут 192.168.1.0/24 Approved на 3apa3aserver.
#
#   ./scripts/set-mobile-env-home-lan.sh

set -euo pipefail

BASE="http://192.168.1.67:8000"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ROOT}/mobile-app/.env"
EXAMPLE="${ROOT}/mobile-app/.env.example"

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$EXAMPLE" "$ENV_FILE"
fi

upsert() {
  local key="$1" val="$2" file="$3"
  if grep -q "^${key}=" "$file" 2>/dev/null; then
    sed -i.bak "s|^${key}=.*|${key}=${val}|" "$file" && rm -f "${file}.bak"
  else
    printf '\n%s=%s\n' "$key" "$val" >>"$file"
  fi
}

upsert "EXPO_PUBLIC_API_BASE_URL" "$BASE" "$ENV_FILE"
upsert "EXPO_PUBLIC_USE_MOCK_API" "false" "$ENV_FILE"
upsert "EXPO_PUBLIC_USE_MOCK_DATA" "false" "$ENV_FILE"
upsert "EXPO_PUBLIC_DEBUG_TOUCH" "false" "$ENV_FILE"

echo "Обновлён ${ENV_FILE}:"
grep -E '^EXPO_PUBLIC_' "$ENV_FILE" || true
echo ""
echo "Проверка (нужен Tailscale + subnet): Safari → ${BASE}"
echo "Пересборка на iPhone: ./scripts/rebuild-ios-device.sh"
