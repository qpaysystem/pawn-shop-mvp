#!/usr/bin/env bash
# Удалённый деплой на Timeweb по SSH после git push.
#
# 1) Скопируйте: cp scripts/deploy-remote.example.sh scripts/deploy-remote.sh
# 2) Подставьте переменные ниже или задайте в окружении перед запуском.
# 3) Добавьте scripts/deploy-remote.sh в .gitignore (уже игнорируется маской ниже — см. комментарий).
#
# Использование из корня репозитория:
#   export DEPLOY_SSH="cf89938@vh430.timeweb.ru"
#   export DEPLOY_DIR="~/pawn-shop-mvp"
#   ./scripts/deploy-remote.sh
#
# Либо отредактируйте значения по умолчанию:

DEPLOY_SSH="${DEPLOY_SSH:-ЛОГИН@ХОСТ.timeweb.ru}"
DEPLOY_DIR="${DEPLOY_DIR:-~/pawn-shop-mvp}"

set -e
cd "$(dirname "$0")/.."

if [[ "$DEPLOY_SSH" == *"ЛОГИН@"* ]]; then
    echo "Задайте DEPLOY_SSH (и при необходимости DEPLOY_DIR) или отредактируйте скрипт."
    exit 1
fi

git push origin main
ssh "$DEPLOY_SSH" "cd $DEPLOY_DIR && git pull origin main && bash deploy.sh"

echo "Готово."
