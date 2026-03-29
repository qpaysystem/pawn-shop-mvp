#!/bin/bash
# Сканирование портов в поисках MS SQL / PostgreSQL на серверах 1С (запускать при включённом VPN).
# Использование: ./scripts/scan_lmb_db_ports.sh [host]
# По умолчанию: 192.168.7.1 (1c-dl380g7). Можно передать 5.128.186.3 или 1c-dl380g7.

HOST="${1:-192.168.7.1}"
TIMEOUT=2

# Порты: MS SQL (1433, 1434, 4022, динамические 49152+), PostgreSQL 5432, MySQL 3306, типичные веб
PORTS="80 443 135 139 445 3306 4022 1433 1434 1435 1436 1437 1438 1439 1440 5432 2382 2383 5022 49152 49153 49154 49155 49156 49157 49300 49301 49302 8080 8443"

echo "Сканирование хоста: $HOST (таймаут ${TIMEOUT}с)"
echo ""

OPEN=""
for p in $PORTS; do
  if nc -zv -w $TIMEOUT "$HOST" "$p" 2>/dev/null; then
    OPEN="$OPEN $p"
    case "$p" in
      1433)   echo "  -> $p (MS SQL default)" ;;
      1434)   echo "  -> $p (MS SQL Browser)" ;;
      5432)   echo "  -> $p (PostgreSQL)" ;;
      3306)   echo "  -> $p (MySQL)" ;;
      80)     echo "  -> $p (HTTP)" ;;
      443)    echo "  -> $p (HTTPS)" ;;
      *)      echo "  -> $p" ;;
    esac
  fi
done

echo ""
if [ -z "$OPEN" ]; then
  echo "Открытых портов из списка не найдено. SQL-сервер может быть на другом хосте или порт закрыт фаерволом."
else
  echo "Открытые порты:${OPEN}"
fi
