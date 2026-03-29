# Ломбард MVP — система управления сетью ломбардов и комиссионных магазинов

Минимально жизнеспособный продукт (MVP) на Laravel 10: учёт товаров, клиентов, договоров залога и комиссии, ролевая модель доступа.

## Требования

- PHP 8.1+
- MySQL 8.0+ или PostgreSQL
- Composer (или локальный `composer.phar`)

## Установка

1. **Клонировать/скопировать проект** в каталог `pawn-shop-mvp`.

2. **Установить зависимости.**

   Если команда `composer` доступна:
   ```bash
   cd pawn-shop-mvp
   composer install
   ```

   **Если `composer: command not found`** — скачайте Composer в каталог проекта и запускайте его через `php`:
   ```bash
   cd pawn-shop-mvp
   curl -sS https://getcomposer.org/installer | php
   php composer.phar install
   ```
   Дальше в проекте используйте `php composer.phar` вместо `composer` (например: `php composer.phar update`).

3. **Настроить окружение:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   В `.env` указать доступ к БД:
   ```
   DB_CONNECTION=mysql
   DB_DATABASE=pawn_shop_mvp
   DB_USERNAME=...
   DB_PASSWORD=...
   ```

4. **Выполнить миграции и сидеры:**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

5. **Создать симлинк для загрузки файлов (фото товаров):**
   ```bash
   php artisan storage:link
   ```
   При необходимости создать каталог для фото вручную:
   ```bash
   mkdir -p storage/app/public/items
   ```

6. **Запуск (локально):**
   ```bash
   php artisan serve
   ```
   Открыть в браузере: http://localhost:8000

## Обзор проекта (архитектура, риски, планы)

См. **[docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md)** — как всё связано, что не доделано, на что смотреть и следующие шаги.

## Интеграция 1С и паспортов (Cursor)

- В **Cursor**: Command Palette → **Tasks: Run Task** → задачи **LMB: sync …** (см. `.vscode/tasks.json`) или команды в терминале из корня проекта.
- Подробности и переменные окружения: **`.cursor/rules/lmb-1c-passport-sync.mdc`**, **`docs/LMB_1C_CONTRAGENT_FULL_DATA.md`**.

```bash
php artisan lmb:sync-contragents --backfill-passports-from-inforeg
php artisan lmb:sync-passports-from-1c
```

## Выкладка на боевой хостинг

- Репозиторий: **https://github.com/qpaysystem/pawn-shop-mvp** (`main`).
- Полная инструкция (Timeweb, SSH, БД): **[DEPLOY.md](DEPLOY.md)**.
- Обновление на сервере: в каталоге проекта `./deploy.sh` (миграции, кэш, composer).
- С вашего Mac: `git push origin main`, затем на сервере `git pull` и `./deploy.sh`, либо шаблон **`scripts/deploy-remote.example.sh`** → `scripts/deploy-remote.sh` с переменной `DEPLOY_SSH`.
- SFTP: шаблон **`.vscode/sftp.json.example`** → `.vscode/sftp.json` (не коммитить).

## Тестовые пользователи (после `db:seed`)

| Роль           | Email               | Пароль   |
|----------------|---------------------|----------|
| Супер-админ    | admin@example.com   | password |
| Оценщик        | appraiser@example.com | password |

## Роли

- **super-admin** — полный доступ ко всем магазинам, управление магазинами и пользователями.
- **manager** — полный доступ к своему магазину.
- **appraiser** — приём товара (договоры залога и комиссии).
- **cashier** — оформление продаж и выкупов.
- **storekeeper** — смена статуса товара и места хранения.

Каждый пользователь (кроме super-admin) привязан к одному магазину (`store_id`).

## Основной поток

1. **Приём товара** — раздел «Приём товара»: выбор типа договора (Залог/Комиссия), поиск или создание клиента, данные товара, фото, суммы и сроки. После сохранения создаются клиент (если новый), товар, договор и открывается печатная форма.
2. **Товары** — список с фильтрами, карточка товара с фото, историей статусов и привязанным договором.
3. **Договоры залога/комиссии** — списки, просмотр, печать, оформление выкупа/продажи.

## Структура БД (основное)

- **stores** — магазины
- **users** — сотрудники (роль, привязка к магазину)
- **item_categories**, **brands**, **item_statuses**, **storage_locations** — справочники
- **clients** — клиенты
- **items** — товары (штрихкод, фото JSON, цены, статус, место хранения)
- **pawn_contracts**, **commission_contracts** — договоры
- **item_status_history** — история смены статусов товара

Защита: CSRF, валидация форм, разграничение по ролям и магазинам.
