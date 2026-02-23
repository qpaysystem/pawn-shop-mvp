#!/usr/bin/env bash
# Скрипт обновления приложения на боевом сервере (запускать из корня проекта).
# Использование: ./deploy.sh

set -e

echo "=== Деплой pawn-shop-mvp ==="

# Composer: предпочитаем локальный composer.phar, иначе системный
if [ -f composer.phar ]; then
    COMPOSER="php composer.phar"
else
    COMPOSER="composer"
fi

echo "1. Обновление кода (git pull)..."
git pull --no-edit || true

echo "2. Установка зависимостей (без dev)..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "3. Миграции..."
php artisan migrate --force

echo "4. Кэш конфигурации и маршрутов..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "5. Симлинк storage (если ещё нет)..."
php artisan storage:link 2>/dev/null || true

echo "=== Готово. Проверьте сайт. ==="
