#!/usr/bin/env bash
# API ломбарда с iPhone по белому IP (LTE / вне LAN), без Tailscale.
# См. client-management-crm/infra/docs/home-network-remote-access.md
#
#   ./scripts/set-mobile-env-wan.sh
#   ./scripts/set-mobile-env-wan.sh 37.193.61.182 18082

set -euo pipefail

WAN_IP="${1:-37.193.61.182}"
PORT="${2:-18082}"
BASE="http://${WAN_IP}:${PORT}"

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
echo "Проверка API:"
curl --noproxy '*' -s -o /dev/null -w "HTTP %{http_code}\n" -m 8 -X POST "${BASE}/api/v1/auth/login" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"appraiser@example.com","password":"password","device_name":"wan-check"}' || true
echo ""
echo "Дальше пересоберите приложение на iPhone:"
echo "  ./scripts/rebuild-ios-device.sh"
