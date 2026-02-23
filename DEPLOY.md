# Пошаговая инструкция: запуск на боевом хостинге

Инструкция для выкладки проекта **pawn-shop-mvp** на боевой сервер (на примере Timeweb по SSH). Все шаги выполняются по порядку.

---

## Что понадобится заранее

- Доступ по **SSH** к хостингу (логин, пароль или ключ, адрес сервера — смотрите в панели хостинга).
- **Домен** или временный адрес сайта (например `ваш-сайт.timeweb.ru`).
- В панели хостинга нужно будет **создать базу MySQL** и записать: имя БД, пользователь, пароль, хост (часто `localhost`).

---

## Часть 1. Подготовка на вашем компьютере

### Шаг 1. Собрать файлы проекта

Убедитесь, что у вас есть папка проекта **pawn-shop-mvp** со всеми файлами (app, config, public, routes, storage, composer.json и т.д.). Файл `.env` на сервер не заливаем — он создаётся на хостинге.

**Если используете Git:** репозиторий должен быть запушен, на хостинге будете клонировать по SSH.

**Если без Git:** подготовьте архив проекта (без папки `.git` и без `.env`):

```bash
# В каталоге, где лежит папка pawn-shop-mvp:
tar --exclude='.git' --exclude='.env' -czf pawn-shop-mvp.tar.gz pawn-shop-mvp
```

Архив `pawn-shop-mvp.tar.gz` понадобится для загрузки на сервер.

---

## Часть 2. Действия в панели хостинга (до SSH)

### Шаг 2. Создать базу данных MySQL

1. Войдите в панель управления хостингом (Timeweb: hosting.timeweb.ru).
2. Откройте раздел **«Базы данных»** (или «MySQL»).
3. Создайте новую базу данных.
4. Создайте пользователя БД с полными правами на эту базу (или используйте предложенного пользователя).
5. Запишите и сохраните у себя:
   - **Имя базы** (например `u1234567_lombard`)
   - **Пользователь БД**
   - **Пароль БД**
   - **Сервер БД** (часто `localhost` или указан в панели)

Позже эти данные будут прописаны в файле `.env` на сервере.

### Шаг 3. Узнать данные для SSH

В панели найдите раздел **«SSH»** или **«Доступ по SSH»**. Запишите:

- **Хост** (например `ваш-логин.timeweb.ru` или IP)
- **Логин** (например `u1234567`)
- **Пароль** или способ входа по ключу

Подключение будет таким: `ssh логин@хост`.

---

## Часть 3. Подключение к серверу и загрузка файлов

### Шаг 4. Подключиться к серверу по SSH

На вашем компьютере откройте терминал и выполните (подставьте свой логин и хост):

```bash
ssh u1234567@ваш-сервер.timeweb.ru
```

Введите пароль, если спросит. Вы окажетесь в домашнем каталоге на сервере (например `/home/u1234567`).

### Шаг 5. Создать каталог для сайта

На сервере (после подключения по SSH):

```bash
cd ~
mkdir -p pawn-shop-mvp
```

Каталог `pawn-shop-mvp` будет корнем проекта.

### Шаг 6. Загрузить файлы проекта на сервер

**Способ А — через Git (если на хостинге есть Git):**

На сервере:

```bash
cd ~/pawn-shop-mvp
git clone https://ваш-репозиторий.git .
```

Точка в конце команды важна — клонирование идёт в текущую папку.

**Способ Б — через SCP с вашего компьютера (второй терминал, не закрывая SSH):**

На **вашем компьютере** (в каталоге, где лежит архив или папка проекта):

```bash
# Если загружаете архив:
scp pawn-shop-mvp.tar.gz u1234567@ваш-сервер.timeweb.ru:~/
```

Затем снова на **сервере**:

```bash
cd ~
tar xzf pawn-shop-mvp.tar.gz
# Если в архиве была папка pawn-shop-mvp, то файлы уже в ~/pawn-shop-mvp
# Если распаковалось иначе — переместите файлы в ~/pawn-shop-mvp так, чтобы внутри были папки app, config, public, routes и т.д.
ls -la pawn-shop-mvp
```

Должны быть видны папки: `app`, `config`, `database`, `public`, `resources`, `routes`, `storage`, файлы `artisan`, `composer.json`.

---

## Часть 4. Настройка Laravel на сервере (по шагам)

Все следующие команды выполняются **на сервере** по SSH, из корня проекта.

### Шаг 7. Сделать симлинк public_html → public (обязательно для Timeweb)

На сервере:

```bash
cd ~
rm -rf pawn-shop-mvp/public_html
ln -s ~/pawn-shop-mvp/public ~/pawn-shop-mvp/public_html
```

Так хостинг будет отдавать в браузере содержимое папки `public` Laravel.

### Шаг 8. Установить Composer и зависимости PHP

```bash
cd ~/pawn-shop-mvp
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
php artisan package:discover
```

Дождитесь окончания. Должна появиться папка `vendor`. После `composer install --no-dev` выполните `php artisan package:discover`, чтобы кэш в `bootstrap/cache/` содержал только установленные пакеты — иначе возможна ошибка *Class "NunoMaduro\Collision\...\CollisionServiceProvider" not found*.

### Шаг 9. Создать файл .env

```bash
cp .env.example .env
php artisan key:generate
```

Ключ приложения будет записан в `.env` автоматически.

### Шаг 10. Заполнить .env для боевого режима

Откройте файл для редактирования:

```bash
nano .env
```

Измените или проверьте следующие строки (подставьте свои данные):

```env
APP_NAME="Ломбард MVP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ваш-домен.ru

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=имя_вашей_базы
DB_USERNAME=пользователь_базы
DB_PASSWORD=пароль_базы
```

- `APP_URL` — точный адрес сайта (с https:// или http://), без слэша в конце.
- `DB_*` — те данные, что вы записали в шаге 2.

Сохранить в nano: **Ctrl+O**, Enter, затем выход: **Ctrl+X**.

### Шаг 11. Запустить миграции и сидер

```bash
php artisan migrate --force
php artisan db:seed --force
```

Таблицы в БД созданы, добавлены тестовые пользователи и справочники.

### Шаг 12. Создать симлинк storage и папку для фото

```bash
php artisan storage:link
mkdir -p storage/app/public/items
```

### Шаг 13. Включить кэш (для production)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Шаг 14. Права на запись (если появятся ошибки 500 при работе сайта)

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Часть 5. Привязка домена в панели хостинга

### Шаг 15. Создать или привязать сайт к каталогу проекта

1. В панели хостинга откройте **«Сайты»** → **«Мои сайты»** (или аналог).
2. Создайте новый сайт или привяжите домен к существующему.
3. В качестве **корневой директории сайта** укажите каталог, где лежит проект, например:
   - `pawn-shop-mvp`  
   или полный путь вроде `/home/u1234567/pawn-shop-mvp`.

Хостинг будет искать в этой директории папку `public_html` и отдавать её содержимое. Мы сделали симлинк `public_html` → `public`, поэтому отдаётся папка Laravel `public` — это правильно.

4. Сохраните настройки. Домен должен начать открываться (может потребоваться 1–2 минуты или перезапуск сервиса).

---

## Часть 6. Проверка

### Шаг 16. Открыть сайт в браузере

Откройте в браузере адрес вашего сайта (тот же, что в `APP_URL`).

- Должна открыться страница входа в систему.
- **Логин:** `admin@example.com`
- **Пароль:** `password`

(Эти данные создаются сидером на шаге 11. После первого входа рекомендуется сменить пароль.)

Если видите ошибку 500 — проверьте права (шаг 14) и логи: на сервере `cat storage/logs/laravel.log` (последние строки).

---

## Краткая шпаргалка: только команды на сервере (после загрузки файлов)

Если вы уже загрузили файлы в `~/pawn-shop-mvp` и создали БД, можно выполнить подряд:

```bash
cd ~
rm -rf pawn-shop-mvp/public_html
ln -s ~/pawn-shop-mvp/public ~/pawn-shop-mvp/public_html

cd ~/pawn-shop-mvp
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
php artisan package:discover
cp .env.example .env
php artisan key:generate
nano .env   # заполнить APP_URL, DB_* и сохранить
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
mkdir -p storage/app/public/items
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

После этого в панели привязать домен к каталогу `pawn-shop-mvp` и проверить сайт.

---

## Git на Timeweb (деплой через git pull)

Если проект уже выложен **без Git** (архивом), можно подключить репозиторий и дальше обновлять сайт через `git pull`.

### 1. Проверить, что Git установлен

По SSH:

```bash
git --version
```

Если команды нет — в панели Timeweb посмотрите, доступна ли установка Git или обратитесь в поддержку.

### 2. Подключить репозиторий к существующей папке проекта

На сервере проект лежит в `~/pawn-shop-mvp` без истории Git. Инициализируем репозиторий и привязываем `origin`:

```bash
cd ~/pawn-shop-mvp
git init
git remote add origin https://github.com/qpaysystem/pawn-shop-mvp.git
git fetch origin main
git reset --hard origin/main
```

- Если репозиторий **приватный**, вместо `https://github.com/...` понадобится авторизация (см. шаг 4).
- После `git reset --hard` несохранённые локальные изменения на сервере пропадут; файл `.env` будет перезаписан из репозитория, если он там есть. Чтобы **не затирать .env**, перед `git reset` сделайте копию: `cp .env .env.backup`, а после — восстановите: `mv .env.backup .env`.

Безопасный вариант (сохранить .env и vendor):

```bash
cd ~/pawn-shop-mvp
cp .env .env.local
git init
git remote add origin https://github.com/qpaysystem/pawn-shop-mvp.git
git fetch origin main
git reset --hard origin/main
mv .env.local .env
```

### 3. Если настраиваете Git «с нуля» (новая копия проекта)

Сделать клон в домашнюю директорию:

```bash
cd ~
git clone https://github.com/qpaysystem/pawn-shop-mvp.git pawn-shop-mvp
cd pawn-shop-mvp
```

Дальше: симлинк `public_html`, Composer, `.env`, миграции, кэш — как в основной инструкции выше.

### 4. Доступ к приватному репозиторию GitHub

**Вариант А — по HTTPS с токеном**

1. GitHub → Settings → Developer settings → Personal access tokens → создать токен (права `repo`).
2. На сервере при первом `git fetch` или `git pull` указать логин и вместо пароля — токен.

Или сохранить URL с токеном (не показывать токен в логах):

```bash
git remote set-url origin https://ВАШ_ЛОГИН:ТОКЕН@github.com/qpaysystem/pawn-shop-mvp.git
```

**Вариант Б — по SSH (deploy key)**

1. На сервере сгенерировать ключ (если нет):  
   `ssh-keygen -t ed25519 -C "timeweb" -f ~/.ssh/id_ed25519_github -N ""`
2. Вывести публичный ключ:  
   `cat ~/.ssh/id_ed25519_github.pub`  
   Скопировать вывод.
3. В GitHub: репозиторий → Settings → Deploy keys → Add deploy key → вставить ключ.
4. На сервере переключить remote на SSH и проверить:

```bash
cd ~/pawn-shop-mvp
git remote set-url origin git@github.com:qpaysystem/pawn-shop-mvp.git
GIT_SSH_COMMAND="ssh -i ~/.ssh/id_ed25519_github -o StrictHostKeyChecking=accept-new" git fetch origin main
```

При необходимости в `~/.ssh/config` добавить:

```
Host github.com
  IdentityFile ~/.ssh/id_ed25519_github
  StrictHostKeyChecking accept-new
```

### 5. Обновление сайта после изменений в коде

**Как перенести в Git и сразу на боевой:**

1. **У себя на компьютере** (в папке проекта):
   ```bash
   git add .
   git commit -m "описание изменений"
   git push origin main
   ```

2. **На сервере по SSH** — один из вариантов:

   **Вариант А — скрипт (рекомендуется):**
   ```bash
   cd ~/pawn-shop-mvp
   git pull origin main
   ./deploy.sh
   ```
   В `deploy.sh` уже учтён вызов `php composer.phar`, если он лежит в каталоге проекта.

   **Вариант Б — команды вручную:**
   ```bash
   cd ~/pawn-shop-mvp
   git pull origin main
   /usr/bin/php composer.phar install --no-dev --optimize-autoloader
   /usr/bin/php artisan package:discover
   /usr/bin/php artisan migrate --force
   /usr/bin/php artisan config:cache
   /usr/bin/php artisan route:cache
   ```

   **Вариант В — с локальной машины одной строкой** (подставьте свой логин и хост):
   ```bash
   git push origin main && ssh cf89938@vh430.timeweb.ru 'cd ~/pawn-shop-mvp && git pull origin main && ./deploy.sh'
   ```
   Тогда после `git push` сразу выполнится подключение по SSH и деплой на сервере.

**Важно:** после `git pull` не перезаписывайте `.env` из репозитория — на сервере должны оставаться свои `APP_KEY`, `DB_*`, `LOG_CHANNEL` и т.д. Если в репозитории есть `.env.example`, используйте его только как образец.

**Если на сервере `git pull` ругается на local changes или untracked files:**

Такое бывает, если на сервере менялся лог, кэш или появилась лишняя миграция. Разблокировать обновление можно так (по SSH):

```bash
cd ~/pawn-shop-mvp
# Удалить кэш Laravel (в репо эти файлы больше не хранятся — они мешают pull)
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
# Сбросить лог, чтобы pull не ругался (файл пересоздастся при работе сайта)
git checkout -- storage/logs/laravel.log 2>/dev/null || true
# Убрать мешающий untracked-файл миграции, если он совпадает с версией из репо
rm -f database/migrations/2025_02_11_100002_add_role_and_store_to_users_table.php
# При необходимости задать ветку по умолчанию (один раз)
git branch --set-upstream-to=origin/main main
# Теперь подтянуть код и задеплоить
git pull origin main
./deploy.sh
```

После успешного `git pull` ошибка *Another route has already been assigned name [clients.store]* при `route:cache` обычно исчезает (она возникала из-за старой версии кода на сервере).

---

## Другой хостинг (не Timeweb)

Если хостинг другой, но есть SSH:

- Вместо симлинка `public_html` настройте в панели **Document Root** на папку `public` проекта (например `.../pawn-shop-mvp/public`).
- Остальные шаги (Composer, .env, миграции, storage:link, кэш) — те же.

Если хостинг без SSH (только FTP и панель): создайте БД, залейте файлы так, чтобы корень сайта указывал на папку `public`, загрузите готовый `.env` (сгенерируйте `APP_KEY` локально: `php artisan key:generate --show`). Запуск миграций возможен через «Выполнить PHP» в панели или по инструкции вашего хостинга.

---

## Опционально: переменные .env для дополнительных функций

Для лендинга, API распознавания, интеграций можно позже добавить в `.env`:

- `LOMBARD_NAME`, `LOMBARD_PHONE` — название и телефон в шапке/футере.
- `GEMINI_API_KEY` или `GOOGLE_VISION_API_KEY` — распознавание текста с фото.
- `OPENAI_API_KEY`, `DEEPSEEK_API_KEY` — паспорт, транскрипция.
- `SERPER_API_KEY` — поиск объявлений.
- И другие по необходимости (см. `.env.example`).

Без них приложение работает; дополнительные функции просто будут недоступны.
