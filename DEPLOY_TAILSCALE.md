# Ломбард: домашний сервер + Tailscale + мобильное приложение

Цель: **не держать Laravel и Metro на Mac**, а работать с **домашнего сервера** (`vm-web-host-01`), к которому iPhone ходит по **Tailscale** (как вы уже ходили на Mac `100.124.207.97`).

Боевой Timeweb **не трогаем**. LAN-копия — см. [DEPLOY_HOME_LAN.md](./DEPLOY_HOME_LAN.md).

---

## Схема

```
iPhone (Expo Go)  ──Tailscale──►  домашний сервер (100.x.x.x)
                                      ├─ Laravel :80 или :8000  → /api/v1/auth/*
                                      └─ (опц.) Metro :8081     → разработка UI
```

| Что | Где | Адрес для телефона |
|-----|-----|-------------------|
| **API (прод / тест)** | Docker `lombard-portal` на сервере | `http://<TS-IP-сервера>` или `:8000` |
| **Metro (разработка UI)** | Node на сервере | `exp://<TS-IP-сервера>:8081` |
| **Веб-админка** | Traefik | `http://lombard.home` только в LAN; с телефона через TS — **IP сервера** |

---

## 1. Одна Tailscale-сеть (у вас уже так)

Карта tailnet (аккаунт `qpaysystem@`):

| Устройство | Tailscale IP | Роль |
|------------|--------------|------|
| **iphone-15-pro-max** | 100.117.153.109 | телефон в tailnet |
| **macbook-pro** | 100.124.207.97 | Mac |
| **3apa3aserver** | 100.124.109.58 | Linux, **subnet router** `192.168.1.0/24` |
| **vm-web-host-01** | — (нет TS) | `192.168.1.67` — Laravel в Docker, порт **8000** |

**iPhone уже «внутри» одной сети Tailscale.** До ломбарда он ходит не напрямую, а через **3apa3aserver**, который раздаёт маршрут в домашнюю LAN.

```
iPhone (TS) ──► 3apa3aserver (100.124.109.58)
                      │  subnet 192.168.1.0/24
                      ▼
               vm-web-host-01 (192.168.1.67:8000)
```

**На vm-web-host-01 ставить Tailscale не обязательно** — достаточно subnet router на **3apa3aserver**.

В `mobile-app/.env`:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.67:8000
EXPO_PUBLIC_USE_MOCK_API=false
```

Проверка с iPhone (Safari, Tailscale **Connected**):  
`http://192.168.1.67:8000` — должна открыться главная Laravel.

### Если с iPhone не открывается

1. [Tailscale Admin](https://login.tailscale.com/admin/machines) → **3apa3aserver** → **Subnet routes** → маршрут **`192.168.1.0/24`** должен быть **Approved**.
2. На iPhone: Tailscale включён, то же аккаунт `qpaysystem@`.
3. Mac уже достучался до API по этому пути (проверено) — значит маршрут в tailnet работает.

На **iPhone**: приложение Tailscale → **Connected**.

Проверка с iPhone (Safari): `http://100.64.x.x/` — должна открыться главная Laravel (или 404 nginx, но **не** «нет соединения»).

> Если с TS-IP не открывается, а `http://lombard.home` в LAN работает — Traefik слушает только LAN. См. §2.1.

---

## 2. Laravel на сервере (если ещё не поднят)

Полная установка: [DEPLOY_HOME_LAN.md](./DEPLOY_HOME_LAN.md) (Docker, `lombard-portal`, migrate, seed).

### 2.1. Доступ по Tailscale к HTTP

**Вариант A — порт на хосте (проще для API и тестов)**

В `compose.yml` стека добавьте публикацию (пример):

```yaml
services:
  lombard-nginx:
    ports:
      - "8000:80"   # http://<TS-IP>:8000
```

Перезапуск: `docker compose up -d`.

**Вариант B — Tailscale Serve** (HTTPS на tailnet)

```bash
sudo tailscale serve --bg http://127.0.0.1:80
tailscale serve status
```

Тогда URL будет вида `https://vm-web-host-01.<ваш-tailnet>.ts.net` — его же можно положить в `EXPO_PUBLIC_API_BASE_URL` (без `:8000`).

### 2.2. `.env` Laravel на сервере

```bash
ssh server_ubunta@192.168.1.67
cd /opt/stacks/prod/lombard-portal/app
# APP_URL — для ссылок в письмах/админке; для mobile API важнее доступность по TS-IP
nano .env
```

Минимум для mobile auth после seed:

- `APP_URL=http://100.64.x.x` (ваш TS-IP) или MagicDNS
- `DB_*` как в [env.home-lan.example](./env.home-lan.example)

Проверка API с Mac:

```bash
curl -s -X POST "http://100.64.x.x:8000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"appraiser@example.com","password":"password","device_name":"test"}'
```

Должен вернуться JSON с `token`.

---

## 3. Деплой кода с Mac на домашний сервер

Из корня репозитория:

```bash
chmod +x scripts/deploy-home-lan.sh
export DEPLOY_SSH="server_ubunta@192.168.1.67"
./scripts/deploy-home-lan.sh
```

Скрипт: `rsync` → `app/` на сервере → `docker compose exec lombard-app ./deploy.sh` (composer, migrate, cache).

Обновление только mobile-кода на Mac не нужно для API — достаточно деплоя Laravel.

---

## 4. Мобильное приложение (Expo Go) → API домашнего сервера

На Mac в `mobile-app/.env`:

```env
EXPO_PUBLIC_API_BASE_URL=http://100.64.x.x:8000
EXPO_PUBLIC_USE_MOCK_API=false
EXPO_PUBLIC_DEBUG_TOUCH=false
```

Подставьте **Tailscale IP сервера** (`tailscale ip -4` **на сервере**), не Mac.

Или одной командой (после того как узнали IP):

```bash
./scripts/set-mobile-env-tailscale.sh 100.64.x.x 8000
```

### 4.1. Metro: разработка UI без Mac

**Вариант 1 — Metro на сервере** (сервер всегда включён):

```bash
ssh server_ubunta@192.168.1.67
cd /opt/stacks/prod/lombard-portal/app/mobile-app   # или отдельный клон
npm install
export REACT_NATIVE_PACKAGER_HOSTNAME=$(tailscale ip -4)
npx expo start --lan -c
```

QR будет с IP **сервера**. iPhone в Tailscale сканирует QR.

**Вариант 2 — Metro на Mac, API на сервере** (чаще удобнее):

```bash
cd mobile-app
# .env уже указывает на TS-IP сервера для API
npm run start:tailscale   # Metro на Mac, только UI bundler
```

API-запросы идут на домашний сервер, JS-bundle — с Mac.

**Вариант 3 — tunnel** (если TS капризничает): `npm run start:tunnel`

---

## 5. Ежедневный запуск (кратко)

| Шаг | Команда / действие |
|-----|-------------------|
| 1 | Сервер включён, Docker: `lombard-portal` up |
| 2 | Tailscale на iPhone и на сервере — Connected |
| 3 | API: Safari `http://<TS-IP>:8000` или curl login (§2.2) |
| 4 | Mobile `.env` → `EXPO_PUBLIC_API_BASE_URL=http://<TS-IP>:8000` |
| 5 | `cd mobile-app && npm run start:tailscale` **или** Metro на сервере (§4.1) |
| 6 | Expo Go → новый QR |

Логин: `appraiser@example.com` / `password` (после `db:seed` на сервере).

---

## 6. Отличие от «всё на Mac»

| Раньше (Mac) | Сейчас (домашний сервер) |
|--------------|---------------------------|
| `php artisan serve` на Mac | Laravel в Docker на `vm-web-host-01` |
| `EXPO_PUBLIC_API_BASE_URL=http://100.124.207.97:8000` (Mac TS) | `http://<TS-IP-сервера>:8000` |
| Metro `npm run start:tailscale` на Mac | Metro на Mac **или** на сервере |
| Ошибка «Could not connect» при разных сетях | TS + правильный IP в QR |

---

## 7. Дальше (не обязательно сейчас)

- **EAS Build** — установить APK/IPA с зашитым `EXPO_PUBLIC_API_BASE_URL` домашнего сервера (без Expo Go).
- **HTTPS** — `tailscale serve` или reverse proxy + сертификат.
- Остальные mobile API (залоги, клиенты) — [MOBILE_BACKEND_INTEGRATION_PLAN.md](./MOBILE_BACKEND_INTEGRATION_PLAN.md).
