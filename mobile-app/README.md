# Lombard Mobile (Expo + React Native)

iPhone-приложение для сотрудников ломбарда. Стек: **Expo SDK 54** (совместим с **Expo Go** из App Store), **Expo Router**, **TypeScript**, **Zustand**, **expo-secure-store**.

Спецификация: `../MOBILE_APP_SPEC.md` в корне репозитория.

## Быстрый старт

```bash
cd mobile-app
cp .env.example .env
npm install
npm run start
```

В терминале Expo нажмите `i` для iOS Simulator или отсканируйте QR в **Expo Go** (App Store, SDK 54).

После смены зависимостей: `rm -rf node_modules && npm install`, затем `npx expo start -c` (очистка кэша Metro).

## Режимы API

### Mock mode (по умолчанию)

```env
EXPO_PUBLIC_USE_MOCK_API=true
```

- Auth и остальные данные — in-app mocks.
- Вход: `demo@lombard.local` / `demo` или `appraiser@example.com` / `password`.
- Backend не нужен.

### Real API mode (только auth v1)

```env
EXPO_PUBLIC_USE_MOCK_API=false
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
```

Подключено к Laravel:

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Тестовый пользователь (после `php artisan db:seed`):

- **Email:** `appraiser@example.com`
- **Password:** `password`

Остальные экраны (залоги, клиенты, справочники) пока используют mocks **только если** `USE_MOCK_API=true`. При `false` справочники не загружаются (пустые списки), wizard/catalogs — в следующих итерациях.

### 127.0.0.1 vs LAN / Tailscale на iPhone

| Среда | `EXPO_PUBLIC_API_BASE_URL` |
|-------|----------------------------|
| iOS Simulator на том же Mac | `http://127.0.0.1:8000` |
| Expo Go + Wi‑Fi (та же сеть) | `http://<LAN-IP-Mac>:8000` |
| **Expo Go + Tailscale** (Mac и iPhone в tailnet) | `http://<Tailscale-IP-Mac>:8000` |

На телефоне `127.0.0.1` — это **сам iPhone**, не Mac.

**Tailscale (рекомендуется, если Wi‑Fi гость/разные сети):**

На Mac:

```bash
tailscale ip -4
# пример: 100.124.207.97
```

В `mobile-app/.env`:

```env
EXPO_PUBLIC_API_BASE_URL=http://100.124.207.97:8000
EXPO_PUBLIC_USE_MOCK_API=false
```

На iPhone: приложение **Tailscale** включено (статус Connected).  
Backend на Mac:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Проверка с iPhone (Safari): `http://100.124.207.97:8000` — должна открыться страница Laravel.

## Переменные окружения

| Переменная | Описание |
|------------|----------|
| `EXPO_PUBLIC_API_BASE_URL` | Origin backend (`http://host:8000`); путь `/api/v1` добавляется в коде |
| `EXPO_PUBLIC_USE_MOCK_API` | `true` (default) — mocks; `false` — реальный auth API |
| `EXPO_PUBLIC_APP_NAME` | Название на экране входа |
| `EXPO_PUBLIC_API_TIMEOUT_MS` | Таймаут HTTP (default 30000) |

## Пример `.env` (real auth + simulator)

```env
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
EXPO_PUBLIC_USE_MOCK_API=false
EXPO_PUBLIC_APP_NAME=Ломбард
```

## Пример `.env` (real auth + iPhone в Wi‑Fi)

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.42:8000
EXPO_PUBLIC_USE_MOCK_API=false
```

## Структура

```
app/                    # Expo Router (экраны)
src/
  api/auth.ts           # login / me / logout (mock | real)
  api/http.ts           # fetch + Bearer + errors
  auth/                 # SecureStore, AuthContext, mapUser
  mocks/                # данные при USE_MOCK_API=true
```

## Backend

- **Реализовано:** `/api/v1/auth/*` (Sanctum).
- **Не подключено в app:** pledges, clients, catalogs, photos, PDF — см. `MOBILE_BACKEND_INTEGRATION_PLAN.md`.

## Скрипты

```bash
npm run start      # Expo dev server
npm run ios        # iOS Simulator
npm run typecheck  # tsc --noEmit
npm run qa:smoke   # mock login + wizard store + auth helpers
```

## Сборка в App Store

Сборка и публикация **не настроены** намеренно.
