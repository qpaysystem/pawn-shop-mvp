# Ломбард на домашнем сервере (`lombard.home`)

**Задача:** поднять **параллельную** копию **pawn-shop-mvp** в LAN, не отключая и не меняя боевой сайт на **Timeweb** (`https://todo.cf89938.tmweb.ru/`). **CRM** (`cf89938.tmweb.ru`) в эту схему не входит.

Выкладка в Docker + Traefik на **`vm-web-host-01`**. Примеры стека в репозитории CRM: **`client-management-crm/infra/web-host/lombard-portal/`** (`compose.example.yml`, `nginx/`, `docker/php/`).

## 1. Подготовка стека на web-host

Следуйте **`infra/web-host/lombard-portal/README.md`**: каталог `/opt/stacks/prod/lombard-portal/`, `compose.yml`, `.env` для MySQL, клон в **`app/`**, `docker compose up -d`.

В **AdGuard** добавьте **`lombard.home` → 192.168.1.67** (или ваш IP Traefik).

## 2. Laravel `.env` в контейнере

В каталоге **`app/`** на сервере (смонтирован в контейнер):

```bash
cd /opt/stacks/prod/lombard-portal/app
cp env.home-lan.example .env
# отредактируйте DB_* (как MYSQL_* в корневом .env compose), затем ключ:
cd /opt/stacks/prod/lombard-portal
docker compose exec lombard-app php artisan key:generate
```

Убедитесь, что **`DB_HOST=lombard-mysql`** (имя сервиса в compose).

## 3. Первый деплой внутри PHP-контейнера

Из каталога стека:

```bash
cd /opt/stacks/prod/lombard-portal
docker compose exec lombard-app composer install --no-dev --optimize-autoloader --no-interaction
docker compose exec lombard-app php artisan migrate --force
docker compose exec lombard-app php artisan db:seed --force
docker compose exec lombard-app php artisan storage:link --force
docker compose exec lombard-app mkdir -p storage/app/public/items
docker compose exec lombard-app php artisan config:cache
docker compose exec lombard-app php artisan route:cache
# view:cache при проблемах с путями views можно пропустить
```

После **`db:seed`**: вход **admin@example.com** / **password**, оценщик **appraiser@example.com** / **password** (см. `DEPLOY.md`). Без сидера таблица `users` пуста — форма логина ответит «credentials do not match».

Дальше обновления: **`docker compose exec lombard-app ./deploy.sh`** (если в `app/` есть `.git` и настроен remote), либо те же команды вручную без шага `git pull`.

## 4. Опционально: копия БД с боевого Timeweb

Только если нужны **реалистичные данные в LAN**; боевой сайт **не отключаем**, дамп не мешает работе Timeweb. Иначе пропустите раздел и ограничьтесь **`migrate`/`seed`** в §3.

На **Timeweb** (боевой ломбард):

```bash
mysqldump -h localhost -u USER -p DATABASE > lombard.sql
```

Скопируйте **`lombard.sql`** на `vm-web-host-01` в каталог стека (например `/opt/stacks/prod/lombard-portal/`). Импорт с хоста в контейнер MySQL (подставьте имя базы из `MYSQL_DATABASE`):

```bash
cd /opt/stacks/prod/lombard-portal
set -a && source .env && set +a
docker compose exec -T lombard-mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" pawn_shop < lombard.sql
```

`-T` отключает TTY, поток идёт с **файла на хосте** в stdin `mysql`. Если база ещё пустая и импорт большой, дождитесь окончания без ошибок в выводе.

После импорта при необходимости:

```bash
docker compose exec lombard-app php artisan config:cache
docker compose exec lombard-app php artisan route:cache
```

Проверьте **`APP_URL=http://lombard.home`** в **`app/.env`**.

## 5. Синхронизация из 1С (клиенты, залоги, скупка)

**`vm-web-host-01` (192.168.1.67) не видит PostgreSQL 1С (`192.168.7.250`)** — из Docker на сервере будет timeout. Чтение 1С делаем **с Mac** (VPN/сеть `192.168.7.x`), запись — в MySQL `lombard.home` через SSH-туннель.

В PHP-образе нужен **`pdo_pgsql`** (см. `infra/web-host/lombard-portal/docker/php/Dockerfile`). Переменные 1С в `app/.env` на сервере:

```bash
./scripts/push-lmb-env-to-home-lan.sh
```

Перенос **всех клиентов** с паспортами и реквизитами (с Mac):

```bash
./scripts/sync-lmb-to-home-mysql.sh contragents \
  --backfill-passports-from-inforeg \
  --backfill-identity-from-1c
```

~24k записей, **несколько часов** через туннель. Лог при ручном запуске: `/tmp/lmb-sync-contragents-home.log`. Проверка в UI: **Клиенты** на `http://lombard.home/clients`.

## 6. Звонки MTS (колл-центр)

На Timeweb блок **`MTS_*`** уже в `.env`. На `lombard.home` его нужно один раз перенести (секреты в git не коммитить):

```bash
# с Mac, из корня pawn-shop-mvp (локальный .env с рабочими MTS_*)
chmod +x scripts/push-mts-env-to-home-lan.sh
./scripts/push-mts-env-to-home-lan.sh
```

Скрипт обновит **`/opt/stacks/prod/lombard-portal/app/.env`** и выполнит `mts:debug-response` в контейнере. В UI: **Колл-центр → «Загрузить звонки с MTS»**.

Полный backfill расшифровок и отправки в портал ИИ (один раз после деплоя):

```bash
docker compose exec lombard-app php artisan migrate --force
docker compose exec lombard-app php artisan mts:backfill --days=90 --batch=50
```

Cron каждые 5 мин: `mts:portal-pipeline` (до 50 расшифровок + 50 push в портал за запуск). Лог: `storage/logs/mts-portal-pipeline.log`.

## 7. Обновление с Mac (rsync)

Если на сервере **без git**, можно слить код с Mac (исключая vendor и мусор):

```bash
rsync -avz --delete \
  --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
  --exclude '.env' --exclude 'storage/logs' \
  /Users/evgeny/pawn-shop-mvp/ user@192.168.1.67:/opt/stacks/prod/lombard-portal/app/
```

Затем на сервере: **`docker compose exec lombard-app composer install --no-dev`** и кэши artisan, как в `deploy.sh`.

## 8. Timeweb и CRM

**Боевой ломбард на Timeweb не отключаем** — LAN-версия живёт рядом как отдельный контур. Дамп с Timeweb в §4 — только **опциональная копия данных** для удобства тестов; можно начать и с чистых `migrate`/`seed`. **CRM** на Timeweb в эту инструкцию не входит.
