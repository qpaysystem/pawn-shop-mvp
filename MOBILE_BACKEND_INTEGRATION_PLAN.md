# План интеграции mobile-app с Laravel backend

**Дата:** 2026-05-23  
**Репозиторий:** `pawn-shop-mvp`  
**Mobile:** `mobile-app/` (Expo, `EXPO_PUBLIC_API_BASE_URL` → `/api/v1`)  
**Ограничения:** web session login **не менялся**; реальный API в mobile **ещё не включён** (`EXPO_PUBLIC_USE_MOCK_API` по умолчанию `true`).

---

## Implemented: Mobile Auth v1

**Статус:** реализовано (2026-05-23).

| Method | Path | Auth | Описание |
|--------|------|------|----------|
| POST | `/api/v1/auth/login` | — | `email`, `password` (+ опционально `device_name` для имени Sanctum-токена) → `{ token, user }` |
| GET | `/api/v1/auth/me` | Bearer Sanctum | `{ id, name, email, role }` |
| POST | `/api/v1/auth/logout` | Bearer Sanctum | Отзыв текущего токена → `204` |

**Код:**

- `app/Http/Controllers/Api/V1/AuthController.php`
- `app/Http/Requests/Api/V1/LoginRequest.php`
- `routes/api.php` (группа `prefix('v1')`)

**Тесты:** `tests/Feature/Api/V1/MobileAuthApiTest.php` — `php artisan test --filter MobileAuthApiTest`

**Web:** `POST /login`, `POST /logout` (session) — без изменений.

**Следующий шаг mobile:** при готовности выставить `EXPO_PUBLIC_USE_MOCK_API=false` и проверить login/me/logout (поля `permissions`, `store_id` в `/auth/me` — в следующих итерациях backend).

---

## Current backend stack summary

| Компонент | Факт |
|-----------|------|
| Framework | **Laravel 10** (`laravel/framework ^10.10`), PHP ^8.1 |
| Auth (web) | **Session** + `LoginController` (`POST /login`, `POST /logout`), middleware `auth` |
| Auth (API) | **Laravel Sanctum 3** — `User` uses `HasApiTokens`; **v1 auth** `/api/v1/auth/*` (login/me/logout) |
| API routes | `routes/api.php`, префикс **`/api`** + **`/api/v1`** для mobile auth |
| Web routes | `routes/web.php` — основной операционный контур (Blade) |
| ORM | Eloquent: `User`, `Client`, `Item`, `PawnContract`, `Store`, `ItemCategory`, `Brand`, `ItemStatus`, `StorageLocation`, `CashDocument`, … |
| API Resources | **Нет** отдельных `JsonResource` для mobile |
| PDF | **Нет** — печать залога: Blade `pawn-contracts.print` (HTML) |
| Платежи | Касса (`CashDocument`) создаётся **внутри** web-flow приёма/выкупа; отдельного REST «payments» для mobile **нет** |
| Тарифы | Отдельной сущности «тариф» **нет** — `loan_percent` задаётся вручную в форме приёма |

---

## Auth flow в backend

### Web (сейчас работает)

```
GET  /login          → форма
POST /login          → Auth::attempt(), session regenerate, redirect dashboard/appraiser
POST /logout         → session invalidate (middleware auth)
```

- CSRF обязателен (`web` middleware).
- Роли: `super-admin`, `manager`, `appraiser`, `cashier`, `storekeeper`.
- Права в коде: `User::canCreateContracts()`, `canProcessSales()`, `canManageStorage()`, `allowedStoreIds()`.

### API v1 mobile auth (реализовано)

```
POST /api/v1/auth/login   → Sanctum personal access token + user JSON
GET  /api/v1/auth/me      → auth:sanctum
POST /api/v1/auth/logout  → auth:sanctum, revoke current token
```

### Legacy API (без изменений)

```
POST /api/clients, PUT, DELETE  → auth:sanctum → ClientApiController → 501 Not implemented
GET  /api/clients               → БЕЗ auth (публичный список) — security risk
GET  /api/clients/{id}          → БЕЗ auth (публичный show)
```

### Что ожидает mobile-app

- `Authorization: Bearer {token}`
- `POST /auth/login` → `{ token, user }` (`device_name` в теле)
- `GET /auth/me` → `AuthUser` + `permissions` + опционально `store_name`
- `POST /auth/logout` → отзыв текущего токена
- Хранение токена: **expo-secure-store** (`lombard_api_token`) — только токен, не сессия

**Вывод:** mobile **не может** использовать web session без CSRF/cookies; нужен **новый** контур Sanctum под `/api/v1`.

---

## Existing API endpoint map

Префикс всех маршрутов из `routes/api.php`: **`/api`**.

| Method | Path | Auth | Controller | Статус / примечание |
|--------|------|------|------------|---------------------|
| GET | `/api/clients` | — | `ClientApiController@index` | Пагинация Eloquent, **без** фильтра по store |
| GET | `/api/clients/{client}` | — | `ClientApiController@show` | Сырой JSON модели |
| POST | `/api/clients` | sanctum | `ClientApiController@store` | **501** |
| PUT/PATCH | `/api/clients/{client}` | sanctum | `ClientApiController@update` | **501** |
| DELETE | `/api/clients/{client}` | sanctum | `ClientApiController@destroy` | **501** |
| GET | `/api/internal/agent-teams/mts/*` | token middleware | MTS integration | Не для mobile |

### Web JSON (session + CSRF) — логика есть, API для native нет

| Method | Path | Ответ | Mobile-релевантность |
|--------|------|-------|----------------------|
| GET | `/clients/search?q=` | JSON array клиентов (max 20) | Поиск клиента |
| GET | `/accept/redemption-search?q=` | `{ clients: [...] }` + contracts | Выкуп (post-MVP) |
| POST | `/accept` | Redirect HTML | **Создание залога** (multipart `photos[]`) |
| POST | `/accept/parse-passport` | `{ success, fields, passport_data, ... }` | OCR паспорта |
| POST | `/accept/ai-estimate` | JSON оценка | Post-MVP |

### Web HTML (session) — справочники и залоги

| Method | Path | Назначение |
|--------|------|------------|
| GET | `/accept` | Форма приёма (каталоги в view) |
| GET | `/pawn-contracts` | Список (filter `store_id`, `redeemed`) |
| GET | `/pawn-contracts/{id}` | Карточка |
| GET | `/pawn-contracts/{id}/print` | HTML договор |
| POST | `/pawn-contracts/{id}/redeem` | Выкуп + касса + проводки |
| GET | `/items`, `/items/{id}`, PATCH | CRUD товаров (HTML), не JSON API |
| Resource | `/stores`, `/item-categories`, `/brands`, `/item-statuses`, `/storage-locations` | Админка HTML |

---

## Mobile app expected API map

Базовый URL в mobile: `{host}/api/v1` (см. `mobile-app/src/config/env.ts`).

Источник контракта: `MOBILE_APP_SPEC.md` §7 + реализация в `mobile-app/src/api/*.ts`.

| Область | Mobile path (относительно base) | Файл |
|---------|----------------------------------|------|
| Auth | `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | `auth.ts` |
| Catalogs | `GET /stores`, `/item-categories`, `/brands`, `/item-statuses`, `/storage-locations?store_id=` | `catalogs.ts` |
| Clients | `GET /clients/search?q=`, `POST /clients` | `clients.ts` |
| Tools | `POST /tools/parse-passport` | `clients.ts` (throw if no backend) |
| Pawns | `GET /pawn-contracts`, `GET /pawn-contracts/{id}`, `POST /pawn-contracts` | `pawnContracts.ts` |
| Print | `GET /pawn-contracts/{id}/print?access_token=` | `pawnPrintUrl()` — **черновик** |

### Ожидаемые тела (ключевые)

**Login request:** `{ email, password, device_name }`  
**Login response:** `{ token, user: { id, name, email, role, store_id, store_name?, permissions } }`

**Create pawn (`POST /pawn-contracts`):**

```json
{
  "store_id": 1,
  "visit_purpose": "appraisal",
  "client_id": 123,
  "client": { "last_name", "first_name", "patronymic", "phone", "passport_data" },
  "item": { "name", "description", "category_id", "brand_id", "status_id", "storage_location_id", "initial_price", "current_price" },
  "loan": { "loan_amount", "loan_percent", "loan_date", "expiry_date" }
}
```

**Multipart (если есть фото):** `payload` = JSON string + `photos[]` files (`pawnContracts.ts`).

**List pawns response:** `{ data: PawnContract[], meta: { current_page, last_page, per_page, total } }`  
**PawnContract:** включает `computed_status: active | overdue | redeemed`, вложенные `client`, `item` с `photos: { url, path }[]`.

---

## Gap analysis

### 1. URL и версионирование

| Mobile | Backend сейчас | Gap |
|--------|----------------|-----|
| `/api/v1/*` | `/api/*` (без v1) | Нужен префикс `v1` и namespace `Api\V1` |
| `/auth/login` | — | **Отсутствует** |
| `/clients/search` | `/clients/search` только **web** | Нужен v1 + sanctum |
| `/pawn-contracts` | `/pawn-contracts` только **web** HTML | Нужен JSON v1 |

### 2. Авторизация

| Аспект | Backend | Mobile | Gap |
|--------|---------|--------|-----|
| Механизм | Session cookie | Bearer Sanctum | Полностью разные |
| Login | `POST /login` (web) | `POST /api/v1/auth/login` | Новый endpoint |
| Logout | Session invalidate | Token revoke | Новый endpoint |
| Print URL | Session on `/pawn-contracts/{id}/print` | Query `access_token` | Нужен sanctum или signed URL |

**Риск web:** добавлять v1 **рядом**, не менять `LoginController` и `web` middleware groups.

### 3. Auth / user shape

| Поле | Mobile `AuthUser` | Backend `User` model |
|------|-------------------|----------------------|
| `permissions` | object с 3 boolean | **Нет в JSON** — считать на сервере из методов User |
| `store_name` | optional | Relation `store` — отдать в Resource |
| `device_name` | в login body | Sanctum `createToken($device_name)` |

### 4. Clients

| Операция | Mobile | Backend |
|----------|--------|---------|
| Search | `GET /clients/search?q=` | Web `ClientController@search` — тот же SQL, другой transport |
| Create | `POST /clients` nested `client` в pawn **или** отдельный POST | Web `ClientController@store` (redirect); API **501** |
| Show | не вызывается в MVP | API `GET /api/clients/{id}` публичный |

**Request gaps (create client):**

| Mobile `CreateClientPayload` | Web `ClientController@store` |
|------------------------------|------------------------------|
| `last_name`, `first_name`, `phone` | + обязательный `client_type` (`individual`/`legal`) |
| `passport_data` одной строкой | web также `lmb_*` поля в accept flow |
| — | `phone` **unique** — ошибка 422 при дубликате |

**Response gaps (search):**

- Совпадают поля: `id`, `full_name`, `last_name`, `first_name`, `patronymic`, `phone`, `email` (web отдаёт).
- Mobile ожидает массив или `{ data: [] }` — web отдаёт **голый массив**.

### 5. Pawn contracts — list / show

| Фильтр | Mobile | Web `PawnContractController@index` |
|--------|--------|-------------------------------------|
| Status | `active`, `overdue`, `redeemed` | `redeemed=0/1` только |
| Search `q` | да | **нет** |
| Pagination | `{ data, meta }` | Laravel paginate **flat** в view |
| `computed_status` | required | **нет** — считать: `redeemed` → redeemed; иначе `expiry_date < today` → overdue; else active |
| `redemption_amount` | optional | accessor `getRedemptionAmountAttribute()` на модели |

**Show:** web Blade; mobile нужен JSON с `client`, `item`, `photos` с абсолютными URL.

### 6. Pawn create (критичный разрыв)

| Аспект | Mobile payload | Web `POST /accept` |
|--------|----------------|---------------------|
| Transport | JSON или multipart `payload` + `photos[]` | `multipart/form-data` flat fields |
| Contract type | только pawn (implicit) | `contract_type=pawn` required |
| Item | `item.name` | `item_name` |
| Loan dates | `loan.expiry_date` | `expiry_date_pawn` |
| Client | `client_id` или `client{}` | `client_id` или `client_*` поля |
| Visit | `visit_purpose` | то же |
| Side effects | ожидает JSON contract | CashDocument, Ledger, ClientVisit, redirect print |

**Отдельного API «pledge items create/update» в mobile нет** — item создаётся **внутри** `POST /pawn-contracts` (как в web).  
Web `ItemController@update` — только storage/status для storekeeper; mobile wizard **не вызывает** отдельный item API.

### 7. Photos

| | Mobile | Backend (accept) |
|---|--------|------------------|
| Upload | `photos[]` в multipart | `photos[]` image max **5120 KB** |
| Storage | ожидает `{ url, path }` в API | `storage/app/public/items/*`, в БД JSON **paths** |
| Field name | `photos[]` | `photos[]` — **совпадает** |
| Wrapper | `payload` JSON string | flat fields — **не совпадает** без адаптера |

### 8. Contract / PDF

| | Mobile | Backend |
|---|--------|---------|
| Print | `GET .../print?access_token=` | `GET /pawn-contracts/{id}/print` HTML + **session** |
| PDF | spec `print.pdf` | **отсутствует** |
| Content-Type | WebView / Linking | `text/html` Blade |

### 9. Payments

| Mobile | Backend |
|--------|---------|
| Нет отдельного API | При создании залога: `CashDocument` «Выдача займа»; при redeem: «Возврат займа» + ledger |

Интеграция payments в mobile **не требуется для MVP** создания залога — достаточно воспроизвести side effects в `PawnContractCreationService`.

### 10. Dictionaries / tariffs / branches

| Mobile | Backend |
|--------|---------|
| `GET /stores` | `Store` model, web `StoreController` — нет JSON list API |
| categories, brands, statuses, storage | загружаются в `AcceptItemController::create` |
| tariffs | **нет** — percent вводится вручную |
| branches | = `stores` |

### 11. Parse passport

| Mobile expects | Web `POST /accept/parse-passport` |
|----------------|-----------------------------------|
| `POST /tools/parse-passport` multipart `photo` | field `photo`, max 10240 KB |
| `PassportParseResult` flat fields | `{ success, fields: { last_name, ... }, passport_data }` |

Нужен mapper полей `fields.*` → mobile DTO.

### 12. Legacy `/api/clients`

Mobile README запрещает публичный `GET /api/clients`.  
При интеграции v1: **не использовать**; закрыть публичные маршруты отдельной задачей backend (не блокер mobile adapter).

---

## Recommended mobile API contract

Рекомендация совпадает с **`MOBILE_APP_SPEC.md` §7** — единый префикс **`/api/v1`**, middleware **`auth:sanctum`** (кроме login).

### Принципы

1. **Не трогать** web-маршруты и `AcceptItemController` поведение для Blade — логику вынести в сервис, вызывать из web и v1.
2. Все ответы — **JSON** + Laravel API Resources.
3. Ошибки: `{ message, errors? }`, HTTP 401/403/422.
4. Пагинация: `{ data, meta: { current_page, last_page, per_page, total } }` — как уже ждёт mobile.
5. Фото: абсолютный `url` = `Storage::disk('public')->url($path)`.

### Минимальный контракт MVP (для включения `EXPO_PUBLIC_USE_MOCK_API=false`)

#### Auth

```
POST /api/v1/auth/login
  body: { email, password, device_name }
  → 200 { token, user: { id, name, email, role, store_id, store_name, permissions: { can_create_contracts, can_process_sales, can_manage_storage } } }

GET /api/v1/auth/me
  → 200 { ...user }

POST /api/v1/auth/logout
  → 204
```

#### Catalogs (read-only)

```
GET /api/v1/stores
GET /api/v1/item-categories
GET /api/v1/brands
GET /api/v1/item-statuses
GET /api/v1/storage-locations?store_id={id}
```

Все списки фильтровать по `Auth::user()->allowedStoreIds()` где применимо.

#### Clients

```
GET /api/v1/clients/search?q=   → 200 { data: ClientSearchResult[] }
POST /api/v1/clients            → 201 ClientResource (default client_type=individual)
GET /api/v1/clients/{id}        → 200 (optional MVP)
```

#### Pawn contracts

```
GET /api/v1/pawn-contracts?store_id&status&q&page=
  → 200 { data: PawnContractResource[], meta }

GET /api/v1/pawn-contracts/{id}
  → 200 PawnContractResource (with client, item.photos[])

POST /api/v1/pawn-contracts
  Content-Type: multipart/form-data
  - payload: JSON string (mobile CreatePawnContractPayload)
  - photos[]: optional files
  → 201 PawnContractResource

GET /api/v1/pawn-contracts/{id}/print
  Accept: text/html
  Authorization: Bearer (preferred over query token)
```

#### Tools (phase 2)

```
POST /api/v1/tools/parse-passport  (multipart photo)
```

#### Explicitly post-MVP

- `POST /pawn-contracts/{id}/redeem`
- `GET /pawn-contracts/redemption-search`
- `GET /pawn-contracts/{id}/print.pdf`
- `POST /tools/ai-estimate`
- `PATCH /items/{id}` отдельно от create pawn

---

## Что можно подключить сразу без изменения backend

**Практически ничего** для полноценного mobile flow.

| Endpoint | Почему не «сразу» |
|----------|-------------------|
| `GET /api/clients` | Без auth, нет search, другой pagination shape, не совпадает с mobile types |
| `GET /api/clients/{id}` | Публичный, без `computed_status`, без pawn nested |
| Web `/clients/search` | Session + CSRF — не для React Native |
| Web `POST /accept` | Session + CSRF + flat form + HTML redirect response |
| Web `/pawn-contracts/*/print` | Session cookie, не Bearer |

**Единственное «мягкое» использование:** после появления **только** `POST /api/v1/auth/login` можно вручную проверить Sanctum token против `GET /api/clients` (index) — но это не покрывает ни login UI, ни wizard, ни безопасность.

**Вывод:** перед переключением mobile нужен **блок v1 API** (минимум auth + catalogs + clients search/create + pawn list/create/show + print).

---

## Что нужно добавить в backend

Приоритет = порядок интеграции (ниже).

| # | Задача | Детали |
|---|--------|--------|
| 1 | `routes/api.php` group `prefix('v1')` | Namespace `App\Http\Controllers\Api\V1` |
| 2 | Auth v1 | Login (createToken), me, logout |
| 3 | Policies | `store_id` ∈ `allowedStoreIds()`, роли на create/redeem |
| 4 | `PawnContractCreationService` | Рефактор из `AcceptItemController::store` (pawn branch) |
| 5 | API Resources | User, Client, Item, PawnContract (+ computed_status, photo urls) |
| 6 | Catalog controllers v1 | 5 GET endpoints |
| 7 | Client search/create v1 | Порт логики `ClientController@search` / store |
| 8 | Pawn list/show/create v1 | JSON + multipart |
| 9 | Print v1 | HTML с `auth:sanctum` или signed temporary URL |
| 10 | Закрыть публичный `GET /api/clients` | Breaking для внешних потребителей — оценить отдельно |
| 11 | parse-passport v1 | Обёртка над существующим методом |
| 12 | Feature tests + OpenAPI | `docs/openapi-mobile-v1.yaml` |
| 13 | PDF (optional) | dompdf/snappy |

**Не требуется для MVP mobile:**

- Отдельный REST для payments (касса остаётся server-side при create/redeem).
- Tariff API (percent остаётся в теле займа).
- `PATCH /items/{id}` unless mobile добавит редактирование после приёма.

---

## Риски для web-версии

| Риск | Митигация |
|------|-----------|
| Рефакторинг `AcceptItemController::store` | Вынести в сервис; web вызывает тот же сервис — регрессионные feature-тесты на `POST /accept` |
| Изменение `routes/api.php` | Добавлять **v1**, не менять поведение web routes |
| Закрытие публичного `/api/clients` | Проверить внешних потребителей; deprecate с логом |
| Sanctum tokens | Не влияют на session; отдельная таблица `personal_access_tokens` |
| CORS для Expo | Настроить `config/cors.php` для dev origin — не трогать web Blade |
| Print endpoint dual auth | Не ломать session-print для web; v1 print — отдельный route или `Accept: text/html` + sanctum |
| Multipart vs validation | Form Request должен принимать **оба** формата на переходном этапе или только `payload` для mobile |

---

## Порядок интеграции

### Фаза 0 — подготовка backend (без mobile toggle)

- Сервис создания залога + Resources + Policies.
- Маршруты `/api/v1` + feature tests.
- Mobile **остаётся** на mocks.

### Фаза 1 — auth

| Step | Backend | Mobile (`EXPO_PUBLIC_USE_MOCK_API=false`) |
|------|---------|-------------------------------------------|
| 1.1 | `POST /api/v1/auth/login`, `GET me`, `POST logout` | Проверить login/logout/me, SecureStore |
| 1.2 | UserResource + permissions | Dashboard/Settings показывают user |

**Критерий готовности:** вход appraiser/manager, повторный запуск восстанавливает сессию.

### Фаза 2 — active pledges list

| Step | Backend | Mobile |
|------|---------|--------|
| 2.1 | `GET /api/v1/pawn-contracts` + `computed_status` + pagination meta | Tabs «Залоги», Dashboard stats |
| 2.2 | Фильтры `status`, `q`, `store_id` | Фильтры на pledges screen |

### Фаза 3 — customer search/create

| Step | Backend | Mobile |
|------|---------|--------|
| 3.1 | `GET /api/v1/clients/search` | Wizard step customer (search) |
| 3.2 | `POST /api/v1/clients` | Wizard step customer (create) |

### Фаза 4 — pledge create

| Step | Backend | Mobile |
|------|---------|--------|
| 4.1 | `GET` catalogs v1 | Preload в AuthContext (уже есть вызовы) |
| 4.2 | `POST /api/v1/pawn-contracts` JSON без фото | Review submit |
| 4.3 | multipart + `payload` + `photos[]` | Photos step |

### Фаза 5 — photo upload

| Step | Backend | Mobile |
|------|---------|--------|
| 5.1 | ItemResource с `photos[].url` | Preview in pledge details |
| 5.2 | Валидация 5120 KB, count limit | Align with spec max 5 |

### Фаза 6 — pledge details

| Step | Backend | Mobile |
|------|---------|--------|
| 6.1 | `GET /api/v1/pawn-contracts/{id}` | `pledge/[id].tsx` |
| 6.2 | Вложенные client/item | Все поля карточки |

### Фаза 7 — contract / PDF

| Step | Backend | Mobile |
|------|---------|--------|
| 7.1 | `GET /api/v1/pawn-contracts/{id}/print` + Bearer | WebView или `Linking.openURL` с заголовком (или signed URL) |
| 7.2 | PDF optional | Post-MVP |

### После MVP

- Redemption search + redeem API.
- parse-passport v1.
- Закрытие legacy public clients API.
- `meta/version` forced update.

---

## Mobile-side changes (план, без реализации сейчас)

Когда backend v1 готов, в `mobile-app` (без изменения web):

| Файл | Изменение |
|------|-----------|
| `.env` | `EXPO_PUBLIC_USE_MOCK_API=false`, корректный `API_BASE_URL` |
| `src/config/env.ts` | staging/prod URLs |
| `src/api/auth.ts` | Обработка 401 → logout |
| `src/api/pawnContracts.ts` | `pawnPrintUrl` — Bearer via WebView headers, убрать query token |
| `src/api/clients.ts` | mapper parse-passport response |
| Adapters | Laravel pagination → `PaginatedResponse` если backend отдаёт иначе на переходном этапе |
| Types | `client_type` required on create если backend требует |

---

## Сводная таблица: mobile expectation vs backend today

| Capability | Mobile expects | Backend today | Action |
|------------|----------------|---------------|--------|
| Login token | `/api/v1/auth/login` | Session only | **Add v1** |
| Me / logout | `/api/v1/auth/me`, logout | — | **Add v1** |
| Client search | `/api/v1/clients/search` | Web JSON only | **Add v1** |
| Client create | `POST /api/v1/clients` | API 501 / web form | **Add v1** |
| Pawn list | JSON + status filter | Web HTML | **Add v1** |
| Pawn show | JSON + relations | Web HTML | **Add v1** |
| Pawn create | JSON/multipart nested | Web multipart flat | **Add v1** + service |
| Item update API | Not used in MVP | Web only | Defer |
| Photo upload | `photos[]` + payload | Same in web accept | **Adapter** in v1 |
| Print | Bearer/HTML | Web session HTML | **Add v1** |
| PDF | `.print.pdf` | — | Defer |
| Payments API | — | Internal cash docs | None for MVP |
| Tariffs | — | Manual percent | None |
| Dictionaries | 5 GET endpoints | DB via web views | **Add v1** |
| Parse passport | `/tools/parse-passport` | Web `/accept/parse-passport` | **Add v1** wrapper |

---

## References

- `MOBILE_APP_SPEC.md` — целевой контракт v1
- `MOBILE_APP_AUDIT.md` — исходный аудит web vs mobile
- `MOBILE_APP_QA.md` — QA skeleton (mocks)
- Backend: `routes/web.php`, `routes/api.php`, `AcceptItemController`, `PawnContractController`, `ClientController`, `ClientApiController`
- Mobile: `mobile-app/src/api/*`, `mobile-app/src/types/*`

---

*Документ подготовлен без изменений backend и без включения реального API в mobile.*
