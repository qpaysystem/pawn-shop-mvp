#!/usr/bin/env bash
# Скрипт обновления приложения на боевом сервере (запускать из корня проекта).
# Использование: ./deploy.sh

set -e

# PHP: если в PATH битый путь (например /opt/php56), ищем рабочий PHP
PHP=""
if command -v php &>/dev/null && php -v &>/dev/null; then
    PHP="php"
fi
if [ -z "$PHP" ]; then
    for p in /usr/bin/php /usr/local/bin/php; do
        if [ -x "$p" ] && $p -v &>/dev/null; then
            PHP="$p"
            break
        fi
    done
fi
if [ -z "$PHP" ]; then
    echo "Ошибка: не найден рабочий PHP. Укажите: export PHP=/usr/bin/php && ./deploy.sh"
    exit 1
fi

echo "=== Деплой pawn-shop-mvp ==="

# Composer: предпочитаем локальный composer.phar, иначе системный
if [ -f composer.phar ]; then
    COMPOSER="$PHP composer.phar"
else
    COMPOSER="composer"
fi

echo "1. Обновление кода (git pull)..."
git pull origin main --no-edit || true

echo "2. Установка зависимостей (без dev)..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
$PHP artisan package:discover

echo "3. Миграции..."
$PHP artisan migrate --force

echo "4. Кэш конфигурации и маршрутов..."
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "5. Симлинк storage (если ещё нет)..."
$PHP artisan storage:link 2>/dev/null || true

echo "=== Готово. Проверьте сайт. ==="
