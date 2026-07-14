#!/usr/bin/env bash
# Прописать в mobile-app/.env API домашнего сервера по Tailscale.
# Использование:
#   ./scripts/set-mobile-env-tailscale.sh 100.64.x.x
#   ./scripts/set-mobile-env-tailscale.sh 100.64.x.x 8000

set -euo pipefail

TS_IP="${1:?Укажите Tailscale IP сервера: ./scripts/set-mobile-env-tailscale.sh 100.x.x.x [port]}"
PORT="${2:-8000}"
BASE="http://${TS_IP}:${PORT}"

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
upsert "EXPO_PUBLIC_DEBUG_TOUCH" "false" "$ENV_FILE"

echo "Обновлён ${ENV_FILE}:"
grep -E '^EXPO_PUBLIC_' "$ENV_FILE" || true
echo ""
echo "Дальше: cd mobile-app && npm run start:tailscale"
echo "  (Metro на Mac) или поднимите Metro на сервере — см. DEPLOY_TAILSCALE.md"
