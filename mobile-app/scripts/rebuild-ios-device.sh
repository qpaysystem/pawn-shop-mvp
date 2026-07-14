#!/usr/bin/env bash
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
if [[ ! -f .env ]]; then
  cp .env.example .env 2>/dev/null || true
fi
echo "=== API из .env ==="
grep -E '^EXPO_PUBLIC_' .env 2>/dev/null || true
echo ""
echo "=== Сборка на iPhone (expo run:ios --device) ==="
npx expo run:ios --device
