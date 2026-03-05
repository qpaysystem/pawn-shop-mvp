#!/usr/bin/env bash
# Проверка подключения к API 1С LMB (контрагенты, остатки).
# Запускать с машины, имеющей доступ к серверу 1С (например по VPN).
# Настройки берутся из .env проекта (LMB_USER_API_URL, LMB_USER_API_USERNAME, LMB_USER_API_PASSWORD).

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

echo "=== Проверка 1С LMB ==="
echo "Проект: $PROJECT_DIR"
echo ""

# 1. URL из .env (если есть)
if [ -f .env ]; then
  LMB_URL=$(grep -E '^LMB_USER_API_URL=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'")
  if [ -n "$LMB_URL" ]; then
    echo "URL из .env: $LMB_URL"
    HOST=$(echo "$LMB_URL" | sed -E 's|^https?://([^:/]+).*|\1|')
    echo "Хост: $HOST"
    echo ""
    echo "Пинг (2 сек)..."
    if ping -c 1 -t 2 "$HOST" 2>/dev/null; then
      echo "Пинг: OK"
    else
      echo "Пинг: нет ответа (нормально, если ICMP закрыт)"
    fi
    echo ""
    echo "Порт (таймаут 3 сек)..."
    PORT=$(echo "$LMB_URL" | sed -E 's|^https?://[^:]+:([0-9]+).*|\1|')
    [ -z "$PORT" ] && PORT=5665
    if nc -z -w 3 "$HOST" "$PORT" 2>/dev/null; then
      echo "Порт $PORT: открыт"
    else
      echo "Порт $PORT: недоступен (проверьте VPN и сеть)"
    fi
  fi
fi

echo ""
echo "--- Artisan lmb:ping ---"
php artisan lmb:ping

echo ""
echo "Готово."
