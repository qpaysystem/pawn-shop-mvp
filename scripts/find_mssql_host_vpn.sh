#!/bin/bash
# Поиск хоста MS SQL (порт 1433) и PostgreSQL (5432) внутри сети VPN.
# Запускать при включённом VPN. Сканирует 192.168.7.0/24.
# Использование: ./scripts/find_mssql_host_vpn.sh

TIMEOUT=1
SUBNET="${1:-192.168.7}"
PORTS="1433 5432"

echo "Поиск хостов с портами 1433 (MS SQL) и 5432 (PostgreSQL) в подсети $SUBNET.0/24..."
echo "Таймаут: ${TIMEOUT}с на порт. Подождите."
echo ""

OPEN_COUNT=0
check() {
  h=$1
  p=$2
  if nc -zv -w $TIMEOUT "$h" "$p" 2>/dev/null; then
    case "$p" in
      1433) echo "  MS SQL (1433): $h" ;;
      5432) echo "  PostgreSQL (5432): $h" ;;
      *)    echo "  $h : $p" ;;
    esac
  fi
}
export TIMEOUT PORTS SUBNET
export -f check 2>/dev/null || true

for i in $(seq 1 254); do
  h="$SUBNET.$i"
  for p in $PORTS; do
    ( nc -zv -w $TIMEOUT "$h" "$p" 2>/dev/null && case "$p" in 1433) echo "  MS SQL (1433): $h" ;; 5432) echo "  PostgreSQL (5432): $h" ;; *) echo "  $h : $p" ;; esac ) &
  done
done
wait

echo ""
echo "Сканирование завершено. Если выше есть строки «MS SQL» или «PostgreSQL» — укажите этот LMB_DB_HOST в .env."
echo "Если пусто — хостов с портами 1433/5432 в $SUBNET.0/24 не найдено (другая подсеть: $0 10.0.0)."
