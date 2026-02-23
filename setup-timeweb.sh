#!/usr/bin/env bash
# Первичная настройка проекта на Timeweb по SSH.
# Запускать на сервере из корня проекта: cd ~/pawn-shop-mvp && bash setup-timeweb.sh
# Перед запуском: залить файлы, сделать симлинк (см. DEPLOY.md), создать БД в панели и заполнить .env.

set -e

echo "=== Настройка pawn-shop-mvp на Timeweb ==="

if [ ! -f artisan ]; then
    echo "Ошибка: запускайте скрипт из корня проекта (где лежит artisan)."
    exit 1
fi

if [ ! -f .env ]; then
    echo "Создаю .env из .env.example..."
    cp .env.example .env
    php artisan key:generate
    echo "Отредактируйте .env (APP_URL, DB_*), затем снова запустите этот скрипт."
    exit 0
fi

echo "1. Composer (зависимости без dev)..."
if [ -f composer.phar ]; then
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
else
    composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || {
        echo "Composer не найден. Выполните: curl -sS https://getcomposer.org/installer | php"
        exit 1
    }
fi

echo "2. Миграции..."
php artisan migrate --force

echo "3. Сидер (тестовые пользователи)..."
php artisan db:seed --force

echo "4. Симлинк storage..."
php artisan storage:link 2>/dev/null || true
mkdir -p storage/app/public/items

echo "5. Кэш..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "6. Права на storage и bootstrap/cache..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "=== Готово. Проверьте сайт в браузере (логин: admin@example.com, пароль: password). ==="
