#!/usr/bin/env bash
# Вывести из локального .env все строки MTS_*= (для вставки на хостинг).
# Запуск из корня репозитория или откуда угодно:
#   ./scripts/export-mts-env-for-server.sh
# Сохранить в файл (в .gitignore):
#   ./scripts/export-mts-env-for-server.sh > mts-env.sync.txt
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${1:-$ROOT/.env}"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Файл не найден: $ENV_FILE" >&2
  exit 1
fi
if ! grep -qE '^MTS_[A-Za-z0-9_]+=' "$ENV_FILE"; then
  echo "В $ENV_FILE нет строк вида MTS_*=" >&2
  exit 1
fi
echo "# --- блок MTS для сервера (скопируйте в .env на хостинге или ./scripts/push-mts-env-to-server.sh) ---"
grep -E '^MTS_[A-Za-z0-9_]+=' "$ENV_FILE"
echo "# --- конец блока MTS ---"
