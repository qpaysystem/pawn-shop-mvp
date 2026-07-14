# QA: mobile-app (skeleton iPhone app)

**Дата проверки:** 2026-05-23  
**Путь:** `mobile-app/`  
**Ограничения:** без реального backend, без EAS / App Store.

---

## Итог

| # | Проверка | Результат |
|---|----------|-----------|
| 1 | `npm install` | ✅ OK |
| 2 | `npm run start` (Metro) | ✅ OK (HTTP 200 на :8081, `CI=1`) |
| 3 | `npm run typecheck` | ✅ OK |
| 4 | Mock login `demo@lombard.local` / `demo` | ✅ OK (`npm run qa:smoke`) |
| 5 | Переходы по экранам | ✅ Статически + bundle export |
| 6 | Wizard 6 шагов | ✅ Цепочка маршрутов подтверждена |
| 7 | Zustand внутри wizard | ✅ Smoke-тест + код-ревью |
| 8 | SecureStore только для токена | ✅ OK |
| 9 | `.env.example` / `README.md` | ✅ OK (после правок) |
| 10 | Исправления | ✅ См. раздел «Исправлено» |

**Вердикт:** skeleton готов к ручному прогону в Simulator / Expo Go. Автоматический UI-тест на устройстве в CI не выполнялся (нет `simctl` в среде проверки).

---

## 1. npm install

```text
cd mobile-app && npm install
→ up to date, audited 605 packages, exit 0
```

---

## 2. npm run start

```text
CI=1 npm run start
→ Metro Bundler, Waiting on http://localhost:8081
→ curl http://localhost:8081 → 200
```

Замечания:

- В среде без Xcode: `Unable to run simctl` — не блокирует dev server.
- Для интерактивного режима: `npm run start`, затем `i` (Simulator) или QR в Expo Go.

Дополнительно: `npx expo export --platform web` — **успешно**, все маршруты собраны без ошибок bundler.

---

## 3. npm run typecheck

```text
npm run typecheck → tsc --noEmit, exit 0
```

---

## 4. Mock login

**Автоматически** (`npm run qa:smoke`):

- `mockApi.login({ email: 'demo@lombard.local', password: 'demo' })` → token + user.
- Неверные учётные данные → throw (ожидаемо).

**Учётные данные (mock):**

| Email | Password |
|-------|----------|
| `demo@lombard.local` | `demo` |
| `appraiser@example.com` | `password` |

**Найденная проблема:** без файла `.env` переменная `EXPO_PUBLIC_USE_MOCK_API` не была `true`, mock login через UI шёл бы на реальный API.

**Исправление:** `src/config/env.ts` — моки **включены по умолчанию**, отключение только `EXPO_PUBLIC_USE_MOCK_API=false`.

---

## 5. Экраны и навигация

### Публичная зона

| Экран | Маршрут | Защита |
|-------|---------|--------|
| Splash / redirect | `/` → login или tabs | — |
| Login | `/login` | — |

### Защищённая зона `(app)`

| Экран | Маршрут |
|-------|---------|
| Dashboard | `/(app)/(tabs)/` |
| Active Pledges | `/(app)/(tabs)/pledges` |
| New Pledge (tab → wizard) | `/(app)/(tabs)/new-pledge` → `/(app)/new-pledge` |
| Settings | `/(app)/(tabs)/settings` |
| Pledge Details | `/(app)/pledge/[id]` |

### New Pledge Wizard (6 шагов)

| Шаг | Файл | Переход «Далее» |
|-----|------|-----------------|
| 1 | `new-pledge/index` | → `customer` |
| 2 | `new-pledge/customer` | → `item` |
| 3 | `new-pledge/item` | → `photos` |
| 4 | `new-pledge/photos` | → `loan` |
| 5 | `new-pledge/loan` | → `review` |
| 6 | `new-pledge/review` | submit → pledge / tabs |

Logout: Settings → `router.replace('/login')`, токен очищается в SecureStore.

---

## 6. Wizard: последовательность шагов

Все 6 экранов зарегистрированы в `app/(app)/new-pledge/_layout.tsx`.  
Навигация `router.push` по цепочке **index → customer → item → photos → loan → review** — согласована в коде.

---

## 7. Zustand `pledgeWizardStore`

**Внутри wizard (push между шагами):** состояние в одном глобальном store — **не сбрасывается** при переходах (проверено smoke-тестом: `patchItem`, `setSelectedClient`, `patchLoan` сохраняются в `getState()`).

**Сброс (`reset()`):**

- Вкладка «Приём» — при входе (новая сессия).
- Кнопка «Новый приём залога» на Dashboard — перед `push`.
- После успешного submit на Review.

**Известное ограничение:** если во время wizard переключиться на другую вкладку и снова на «Приём», `reset()` на табе очистит черновик. Это ожидаемо для «нового приёма», не для back внутри stack.

---

## 8. SecureStore

| Место | Использование |
|-------|----------------|
| `src/auth/storage.ts` | Только ключ `lombard_api_token` (save / load / clear) |
| `app.json` plugin | `expo-secure-store` |

`@react-native-async-storage/async-storage` в `package.json` **не используется** в коде (только транзитивная зависимость шаблона). Данные wizard, каталоги, пользователь — в памяти (React state / Zustand), не в SecureStore.

---

## 9. .env.example и README.md

**`.env.example`:** три переменные, комментарий про mock default / `false` для API.

**`README.md`:** быстрый старт, env-таблица, mock-логины, структура, скрипты (`start`, `ios`, `typecheck`, `qa:smoke`). Соответствует фактическому поведению после правки env.

Рекомендация: `cp .env.example .env` — опционально, skeleton работает и без `.env`.

---

## Исправлено в ходе QA

1. **`env.useMockApi`** — default `true` (моки без `.env`).
2. **`scripts/qa-smoke.ts`** + `npm run qa:smoke` — автоматическая проверка login и store.
3. **`components/ExternalLink.tsx`** — cast `href as Href` (typed routes / typecheck).
4. **Dashboard** — явный `resetWizard()` перед «Новый приём залога».
5. **README** — скрипт `qa:smoke`, уточнение про default mock API.

---

## Ручной чеклист (Simulator / Expo Go)

- [ ] Login `demo@lombard.local` / `demo` → Dashboard
- [ ] Tabs: Главная, Залоги, Приём, Профиль
- [ ] Wizard: пройти все 6 шагов, на Review — «Создать договор»
- [ ] Pledge Details из списка / после создания
- [ ] Logout → снова Login
- [ ] Kill app → reopen → сессия восстановлена (mock token в SecureStore)

---

## Real Auth QA Checklist

**Предусловия:** Laravel `php artisan serve --host=0.0.0.0 --port=8000`, пользователь `appraiser@example.com` / `password`.

### `.env` (Simulator)

```env
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
EXPO_PUBLIC_USE_MOCK_API=false
```

Перезапустить Expo (`npm run start`) после смены `.env`.

| # | Шаг | Ожидание |
|---|-----|----------|
| 1 | Login с `appraiser@example.com` / `password` | Dashboard, без mock-подсказки |
| 2 | Settings → API v1 | `http://127.0.0.1:8000/api/v1`, Mock auth **off**, Mock data **on** |
| 3 | Kill app → reopen | Сессия восстановлена (GET `/auth/me` + SecureStore) |
| 4 | Logout | Login screen, повторный вход работает |
| 5 | Неверный пароль | Alert «Неверный email или пароль» |
| 6 | Backend выключен | «Нет связи с сервером…» |

### `.env` (физический iPhone / Expo Go)

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.x.x:8000
EXPO_PUBLIC_USE_MOCK_API=false
```

Не использовать `127.0.0.1` на устройстве.

### Автоматически

```bash
cd mobile-app && npm run qa:smoke && npm run typecheck
```

### После logout (ручная проверка SecureStore)

- Токен удалён — при reopen показывается Login, не Dashboard.

### Ещё не в scope real API

- Список залогов, клиенты, wizard submit — **mock data** (`useMockData=true`).

---

## Не проверялось (ранее)

- Полный Laravel `/api/v1` (кроме auth)
- EAS Build, TestFlight, App Store
- Камера / галерея на физическом iPhone (требует разрешений на устройстве)
- Печать договора (`pawnPrintUrl` — TODO backend)

---

## Команды для повторной проверки

```bash
cd mobile-app
npm install
npm run typecheck
npm run qa:smoke
CI=1 npm run start
```
