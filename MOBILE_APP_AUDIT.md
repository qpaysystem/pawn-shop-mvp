# Mobile App Audit — pawn-shop-mvp

Технический аудит проекта для подготовки **iPhone-приложения сотрудников ломбарда** (оценщик / кассир / товаровед).  
Дата: 2026-05-23. Код приложения не менялся — только этот документ.

---

## 1. Стек backend и frontend

| Слой | Технология |
|------|------------|
| **Backend** | PHP 8.1+, **Laravel 10** |
| **ORM / API** | Eloquent, **Laravel Sanctum** (подключён, почти не используется для mobile) |
| **Основная БД** | **MySQL 8** (по умолчанию; возможен PostgreSQL через `DB_*`) |
| **БД 1С (read-only)** | PostgreSQL, подключение `lmb_1c_pgsql` при `LMB_DB_DRIVER=pgsql` — синк клиентов/договоров, не runtime API для приложения |
| **Frontend (web)** | **Blade** + **Bootstrap 5.3** (CDN), Bootstrap Icons, шрифт Montserrat |
| **Сборка JS** | **Нет** Vite/Webpack/React/Vue — интерактив на vanilla JS в Blade + fetch к web-маршрутам |
| **PWA-задел** | `public/manifest-appraiser.json`, meta Apple Web App, маршрут `/appraiser` — упрощённый web для оценщика, не нативное приложение |
| **Файлы** | `storage/app/public` + `php artisan storage:link`; фото товаров в `items/` |
| **PDF** | **Нет** серверной генерации PDF договоров (только HTML print + `window.print()`) |
| **Excel** | PhpSpreadsheet (импорты, отчёты) |
| **Внешние AI** | DeepSeek / Gemini / Google Vision — распознавание паспорта; AI-оценка (Avito и др.) в `AcceptItemController` |

---

## 2. Авторизация

| Аспект | Реализация |
|--------|------------|
| **Web (основной)** | Session-based: `LoginController` → `Auth::attempt(email, password)` → cookie session |
| **Guard** | `web` (стандартный Laravel) |
| **Logout** | `POST /logout`, инвалидация сессии + CSRF |
| **API (Sanctum)** | `routes/api.php`: `auth:sanctum` только для `apiResource('clients')` — **store/update/destroy = 501** |
| **Публичное API** | `GET /api/clients`, `GET /api/clients/{id}` — **без авторизации** (риск для production) |
| **Internal** | `agent.teams.token` — MTS/колл-центр для `api/internal/agent-teams/*` |
| **CSRF** | Обязателен для всех `web` POST (мобильному клиенту session+cookie неудобен без API-токенов) |

**Вывод для iOS:** нужен отдельный контур **token-based auth** (Sanctum personal access tokens или JWT), login endpoint, refresh, привязка к `store_id` и `role`.

---

## 3. Роли пользователей и сотрудники

### 3.1. `users` — учётные записи входа в систему

Модель: `App\Models\User` (`role` + `store_id`).

| Роль | Константа | Типичные права (методы модели) |
|------|-----------|--------------------------------|
| Супер-администратор | `super-admin` | Все магазины; CRUD пользователей/магазинов |
| Менеджер | `manager` | Полный доступ в рамках своего `store_id` |
| Оценщик | `appraiser` | `canCreateContracts()` — приём, залог/комиссия/скупка |
| Кассир | `cashier` | `canProcessSales()` — выкуп, продажи |
| Кладовщик | `storekeeper` | `canManageStorage()` — статусы и места хранения |

Middleware: `role:super-admin`, `CheckRole` — для web, redirect/403.

### 3.2. `employees` — отдельная сущность

Модель: `App\Models\Employee` — **ФОТ / зарплата**, не логин в приложение. Связь с `stores`, без пароля и без API.

**Для mobile:** авторизовать только **`User`**, не `Employee`.

---

## 4. Бизнес-сущности (текущее состояние)

| Домен | Модель / таблица | Есть в MVP | Комментарий |
|-------|------------------|------------|-------------|
| **Клиенты** | `Client` / `clients` | Да | ФИО, телефон, паспорт, юрлица, blacklist, маркетинг, поля синка 1С (`lmb_*`, `user_uid`) |
| **Залоги (договоры)** | `PawnContract` / `pawn_contracts` | Да | 1 договор ↔ 1 `item`; сумма займа, %, срок, выкуп (`is_redeemed`) |
| **Предметы залога** | `Item` / `items` | Да | Название, категория, бренд, штрихкод, фото JSON, цены, статус, склад |
| **Фото** | `items.photos` (JSON массив путей) | Да | Загрузка при приёме; URL через `/storage/...` |
| **Договоры (прочие)** | `CommissionContract`, `PurchaseContract` | Да | Комиссия и скупка — отдельные потоки |
| **Платежи** | `CashDocument` / `cash_documents` | Да | Касса: приход/расход, тип операции, клиент, магазин |
| **Точки / филиалы** | `Store` / `stores` | Да | Название, адрес, `lmb_store_uid` |
| **Сотрудники (логин)** | `User` | Да | Роль + магазин |
| **Статусы товара** | `ItemStatus` | Да | Справочник: «Принят в ломбард», «На витрине», «Продан», «Выкуплен», «Не выкуплен» |
| **Визиты** | `ClientVisit` | Да | Цель визита, связь с договором |
| **Проводки** | `LedgerEntry` | Да | Бухучёт при залоге/выкупе |
| **Колл-центр, маркетинг, KB** | отдельные модели | Да | Вне scope MVP mobile |

**Связи залога:**

```
Client 1—* PawnContract *—1 Item
PawnContract → Store, User (appraiser), User (redeemed_by)
Item → ItemCategory, Brand, ItemStatus, StorageLocation, Store
```

---

## 5. Существующие API endpoints

Префикс: **`/api`** (`RouteServiceProvider`).

| Метод | URL | Auth | Статус |
|-------|-----|------|--------|
| GET | `/api/clients` | Нет | Работает (paginate) |
| GET | `/api/clients/{client}` | Нет | Работает |
| GET/POST/PUT/DELETE | `/api/clients` (resource) | `auth:sanctum` | GET show/index под sanctum; **POST/PUT/DELETE → 501** |
| GET | `/api/internal/agent-teams/mts/health` | `agent.teams.token` | MTS интеграция |
| GET | `/api/internal/agent-teams/mts/calls` | `agent.teams.token` | MTS интеграция |

### Web JSON (session + CSRF), полезные для mobile-паттерна

| Метод | URL | Назначение |
|-------|-----|------------|
| GET | `/clients/search?q=` | Поиск клиентов (приём) |
| GET | `/accept/redemption-search?q=` | Поиск клиента + активные договоры для выкупа |
| POST | `/accept/parse-passport` | OCR паспорта (multipart) |
| POST | `/accept/ai-estimate` | AI-оценка товара |

**Остальной функционал — только HTML routes** (`routes/web.php`): приём, договоры, касса, товары и т.д.

---

## 6. Как в web создаётся новый залог

**Маршрут:** `GET /accept` → `POST /accept` (`AcceptItemController`).

**Поток (`contract_type = pawn`):**

1. Проверка `Auth::user()->canCreateContracts()` и `store_id ∈ allowedStoreIds()`.
2. **Клиент:** выбор существующего (`client_id`) или создание из ФИО/телефона/паспорта (`findOrCreateClient`).
3. **Товар (`Item`):** название, описание, категория, бренд, статус, место хранения, цены; **фото** → `storage/app/public/items/*`.
4. **История статуса:** `ItemStatusHistory`.
5. **Договор (`PawnContract`):** номер `L-YYYY-NNNNN`, сумма займа, %, даты, `buyback_amount = loan + loan*%`.
6. **Касса:** `CashDocument` «Выдача займа» (расход).
7. **Проводки:** `LedgerService` (займы, залог, касса).
8. **Визит:** `ClientVisit` с `pawn_contract_id`.
9. **Редирект** на `GET /pawn-contracts/{id}/print` (HTML для печати).

**Нет отдельного API** — один большой `POST` multipart form.

---

## 7. Загрузка фото

| Сценарий | Где | Лимит / хранение |
|----------|-----|------------------|
| Фото товара при приёме | `AcceptItemController::store`, поле `photos[]` | `image`, max **5120 KB** каждое; disk `public`, путь `items/...` |
| OCR паспорта | `parsePassportPhoto`, поле `photo` | max **10240 KB**; не сохраняется в карточку автоматически |
| KB статьи | `KnowledgeBaseController` | Отдельный контур |
| Отображение | `Item.photos` JSON → `asset('storage/'.$path)` | |

**Для iOS:** нужны `multipart/form-data` endpoints и стабильные **публичные или signed URL** для просмотра фото.

---

## 8. PDF / договор

| Функция | Есть? |
|---------|-------|
| Серверный PDF (dompdf/mpdf/tcpdf) | **Нет** в runtime-зависимостях приложения |
| Печатная форма залога | **HTML** `resources/views/pawn-contracts/print.blade.php` + кнопка «Печать» |
| Комиссия / скупка | Аналогично `commission-contracts/print`, `purchase-contracts/print` |

**Для iOS:** либо WebView на HTML print URL с session, либо новый endpoint `GET .../pdf` (dompdf/snappy), либо генерация PDF на устройстве из JSON.

---

## 9. Статусы залога (active, overdue, redeemed, sold, cancelled)

### На уровне `PawnContract`

| Состояние | Поле / логика |
|-----------|----------------|
| **Активный** | `is_redeemed = false` |
| **Выкуплен (redeemed)** | `is_redeemed = true`, `redeemed_at`, `redeemed_by` |
| **Просрочен (overdue)** | **Нет отдельного enum** — вычисляется: `!is_redeemed && expiry_date < today` |
| **Продан (sold)** | **Нет** на договоре залога — статус «Продан» у **товара** (`ItemStatus`) |
| **Отменён (cancelled)** | **Нет** поля |

### На уровне `Item` / `ItemStatus`

Справочник (seeder): «Принят в ломбард», «На витрине», «Продан», «Выкуплен», «Не выкуплен».

**Рекомендация для API:** отдавать вычисляемое поле `status: active | overdue | redeemed` + `item_status` из справочника.

---

## 10. Модели данных для iPhone-приложения

Ниже — целевые DTO (можно 1:1 с Eloquent + computed fields).

### Auth

```json
{
  "token": "string",
  "user": {
    "id": 1,
    "name": "string",
    "email": "string",
    "role": "appraiser",
    "store_id": 3,
    "permissions": {
      "can_create_contracts": true,
      "can_process_sales": false,
      "can_manage_storage": false
    }
  }
}
```

### Client

```json
{
  "id": 1,
  "client_type": "individual",
  "full_name": "string",
  "last_name": "string",
  "first_name": "string",
  "patronymic": "string",
  "phone": "string",
  "email": "string|null",
  "passport_data": "string|null",
  "blacklist_flag": false,
  "lmb_passport_issued_by": "string|null",
  "lmb_passport_issued_at": "2020-01-15",
  "lmb_registration_address": "string|null"
}
```

### Item (pawn collateral)

```json
{
  "id": 1,
  "name": "string",
  "description": "string|null",
  "barcode": "I20260420ABC123",
  "category_id": 2,
  "brand_id": 1,
  "status_id": 1,
  "status_name": "Принят в ломбард",
  "storage_location_id": 5,
  "store_id": 3,
  "photos": [
    { "url": "https://host/storage/items/abc.jpg", "path": "items/abc.jpg" }
  ],
  "initial_price": "10000.00",
  "current_price": "10000.00",
  "metal": "string|null",
  "sample": "string|null",
  "weight_grams": "5.500"
}
```

### PawnContract

```json
{
  "id": 1,
  "contract_number": "L-2026-00042",
  "client_id": 1,
  "item_id": 1,
  "store_id": 3,
  "appraiser_id": 2,
  "loan_amount": "5000.00",
  "loan_percent": "20.00",
  "loan_date": "2026-04-20",
  "expiry_date": "2026-05-20",
  "buyback_amount": "6000.00",
  "redemption_amount": "6000.00",
  "is_redeemed": false,
  "redeemed_at": null,
  "computed_status": "active",
  "client": { },
  "item": { }
}
```

### Store, справочники

`Store`, `ItemCategory`, `Brand`, `ItemStatus`, `StorageLocation` — простые id + name (+ color для статуса).

### CashDocument (опционально в MVP+)

```json
{
  "id": 1,
  "document_number": "string",
  "document_date": "2026-04-20",
  "amount": "5000.00",
  "direction": "expense",
  "operation_type_name": "Выдача займа",
  "client_id": 1,
  "store_id": 3
}
```

---

## 11. Чего не хватает в backend для mobile app

| # | Пробел | Критичность |
|---|--------|-------------|
| 1 | **REST API v1** для залогов, приёма, выкупа, справочников | Блокер |
| 2 | **Login + Sanctum token** (без CSRF cookie) | Блокер |
| 3 | **Закрыть публичный** `GET /api/clients` или API key | Высокая |
| 4 | **Единый endpoint создания залога** (client + item + photos + contract + cash) или пошаговый flow | Блокер |
| 5 | **Выкуп** как API (`POST /pawn-contracts/{id}/redeem`) | Высокая |
| 6 | **Список/фильтр договоров** с `computed_status`, пагинация, поиск | Высокая |
| 7 | **Signed URLs** для фото / upload direct to S3 (опционально) | Средняя |
| 8 | **PDF или стабильный print JSON** для договора | Средняя |
| 9 | **Push / offline** — не заложено | Низкая (v2) |
| 10 | **Версионирование API**, OpenAPI/Swagger | Средняя |
| 11 | **Rate limiting** per user (есть 60/min на api group) | Настроить |
| 12 | **Синк с 1С** — только artisan с Mac/сервера; mobile не трогает | — |
| 13 | **Валидация прав по store_id** на каждом API | Блокер |
| 14 | **Тесты API** (PHPUnit/Pest) | Средняя |

---

## 12. Предлагаемый Mobile API contract (v1)

Базовый URL: `https://{host}/api/v1`  
Auth: `Authorization: Bearer {sanctum_token}`  
Формат: JSON; загрузки — `multipart/form-data`.

### Auth

| Method | Path | Body | Response |
|--------|------|------|----------|
| POST | `/auth/login` | `email`, `password`, `device_name` | `token`, `user` |
| POST | `/auth/logout` | — | 204 |
| GET | `/auth/me` | — | `user` |

### Справочники (кэш на устройстве)

| Method | Path |
|--------|------|
| GET | `/stores` — только `allowedStoreIds` |
| GET | `/item-categories` |
| GET | `/brands` |
| GET | `/item-statuses` |
| GET | `/storage-locations?store_id=` |

### Clients

| Method | Path | Примечание |
|--------|------|------------|
| GET | `/clients/search?q=` | min 2 символа |
| GET | `/clients/{id}` | + опционально `include=pawn_contracts` |
| POST | `/clients` | создание при приёме |
| PATCH | `/clients/{id}` | обновление паспорта |

### Pawn contracts

| Method | Path | Примечание |
|--------|------|------------|
| GET | `/pawn-contracts` | `store_id`, `status=active\|overdue\|redeemed`, `q`, `page` |
| GET | `/pawn-contracts/{id}` | с client, item, photos |
| POST | `/pawn-contracts` | **создание залога** (см. ниже) |
| POST | `/pawn-contracts/{id}/redeem` | выкуп (кассир+) |
| GET | `/pawn-contracts/{id}/print` | HTML или PDF |

### Приём залога (рекомендуемый payload `POST /pawn-contracts`)

```json
{
  "store_id": 3,
  "visit_purpose": "appraisal",
  "client_id": null,
  "client": {
    "last_name": "Иванов",
    "first_name": "Иван",
    "patronymic": "Иванович",
    "phone": "+79001234567",
    "passport_data": "50 19 961613"
  },
  "item": {
    "name": "Кольцо золото",
    "description": null,
    "category_id": 1,
    "brand_id": null,
    "status_id": 1,
    "storage_location_id": 2,
    "initial_price": 10000,
    "current_price": 10000
  },
  "loan": {
    "loan_amount": 5000,
    "loan_percent": 20,
    "loan_date": "2026-04-20",
    "expiry_date": "2026-05-20"
  }
}
```

Отдельно: `POST /pawn-contracts` с `multipart` — поля JSON + `photos[]`.

### Redemption search

| Method | Path |
|--------|------|
| GET | `/pawn-contracts/redemption-search?q=` | как web `accept/redemption-search` |

### Media / AI (прокси web-логики)

| Method | Path |
|--------|------|
| POST | `/tools/parse-passport` | multipart `photo` |
| POST | `/tools/ai-estimate` | оценка (опционально MVP+) |

### Ошибки (единый формат)

```json
{
  "message": "Validation failed",
  "errors": { "loan.loan_amount": ["The loan amount field is required."] }
}
```

HTTP: 401 unauthorized, 403 forbidden (role/store), 422 validation, 500 server.

---

## 13. Roadmap разработки iPhone-приложения

### Фаза A — MVP (8–12 недель, при параллельной работе backend)

**Цель:** оценщик в точке может принять залог и найти клиента/договор.

| Backend | iOS |
|---------|-----|
| API v1: auth, stores, справочники | SwiftUI, login, keychain token |
| Clients search/create | Поиск клиента |
| POST pawn-contract + photos | Wizard: клиент → товар → фото → суммы → подтверждение |
| GET pawn-contracts list/show | Список активных залогов точки |
| HTML print в WebView | Просмотр/печать договора (AirPrint) |
| Закрыть публичный clients API | — |

**Вне MVP:** комиссия, скупка, касса, бухгалтерия, маркетинг, колл-центр.

### Фаза B — TestFlight (4–6 недель)

| Backend | iOS |
|---------|-----|
| POST redeem | Выкуп из приложения (роль cashier) |
| `computed_status` overdue | Фильтры, бейджи просрочки |
| PDF endpoint или улучшенный print | Share sheet PDF |
| OpenAPI + staging | Crashlytics, TestFlight beta |
| E2E тесты критичных API | UI-тесты happy path |

**Критерий TestFlight:** 2–3 реальные точки, 5–10 сотрудников, приём + выкуп + печать без web.

### Фаза C — App Store–ready (6–8 недель)

| Тема | Действия |
|------|----------|
| Безопасность | TLS pinning (опционально), token refresh, биометрия, jailbreak detection по политике |
| Производительность | Кэш справочников, сжатие фото на клиенте, pagination |
| Соответствие | Privacy policy, описание сбора паспортных данных, локализация RU |
| Надёжность | Offline queue для черновиков приёма (опционально) |
| Поддержка | Версионирование API, forced update при breaking changes |
| 1С | Read-only справка: показ `lmb_doc_uid` / синк статуса с сервером (не запись в 1С) |

---

## Приложение: карта web-маршрутов (релевантные mobile)

| Действие | Web route |
|----------|-----------|
| Приём | `accept.create`, `accept.store` |
| Поиск выкупа | `accept.redemption-search` |
| Список залогов | `pawn-contracts.index` |
| Карточка залога | `pawn-contracts.show` |
| Печать | `pawn-contracts.print` |
| Выкуп | `pawn-contracts.redeem` |
| Клиенты | `clients.*`, `clients.search` |

---

## Приложение: PWA vs native

Уже есть **PWA manifest для оценщика** (`/appraiser`, standalone). Для App Store и камеры/офлайн/UX нативный SwiftUI предпочтительнее; PWA можно оставить как fallback.

---

## Резюме

Проект — зрелый **Laravel monolith** с сильным **web-операционным контуром** (приём, залог, выкуп, касса, 1С-синк), но **почти без mobile-ready API**. Для iPhone нужно построить **API v1 + Sanctum**, вынести логику из `AcceptItemController` в сервисы/Resources, формализовать **статусы залога** и добавить **безопасную раздачу фото/PDF**.

Следующий практический шаг: согласовать scope MVP (только залог + выкуп) и завести в backend namespace `App\Http\Controllers\Api\V1` + Form Requests + API Resources.
