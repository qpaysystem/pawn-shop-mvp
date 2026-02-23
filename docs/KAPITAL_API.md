# API Kapital — инструкция

**Документация (Swagger):** http://5.128.186.3/api/doc  
**Версия:** 1.0.0 (OAS 3.0)  
**Базовый URL в доке:** в выпадающем списке указан `http://127.0.0.1:8088/api`; для доступа снаружи используйте `http://5.128.186.3/api`.

**Авторизация:** кнопка **Authorize** в Swagger — для защищённых эндпоинтов нужен токен (JWT/Bearer после логина).

---

## 1. Login (вход)

### POST /api/auth/token/login  
**Описание:** Login into the api.

**Request body** (application/json):
```json
{
  "phone": "79998887766"
}
```

**Ответы:**
- **200 OK** — успешный вход (в теле, как правило, access/refresh токены).
- **Invalid JSON** — неверный формат тела.
- **Invalid credentials** — неверные данные (номер не зарегистрирован и т.п.).

---

### POST /api/auth/token/refresh  
**Описание:** Login into the api by refresh token.

Используется для обновления access-токена по refresh-токену (тело/параметры — по схеме в Swagger).

---

## 2. Пользователь (Users)

Эндпоинты требуют авторизации (заголовок с токеном).

| Метод | Путь | Описание |
|-------|------|----------|
| POST | /api/users/me/edit | Редактирование текущего пользователя |
| GET  | /api/users/me | Данные текущего пользователя |
| GET  | /api/users/logout | Выход |
| GET  | /api/users/me/pledge | Залог текущего пользователя (UserOnesGetPledgeResponseDTO) |

---

## 3. Подтверждение телефона

### POST /api/phone_checker/create  
**Описание:** Создание кода для подтверждения номера телефона.

Используется при регистрации/восстановлении доступа по номеру. Точный формат тела запроса и ответа — в Swagger (схема «Создание кода для подтверждения номера телефона»).

---

## 4. Оплата (Payment)

| Метод | Путь | Описание |
|-------|------|----------|
| POST | /api/payment/init | Инициирование оплаты (PaymentInitResponseDTO) |
| POST | /api/payment/notification | Нотификация оплаты (webhook от платёжной системы) |

Схемы: **Инициирование оплаты**, **Нотификация оплаты**, PaymentInitResponseDTO.

---

## 5. Справочники (Pages)

| Метод | Путь | Описание |
|-------|------|----------|
| GET | /api/pages/metal_prices | Цены на металлы (MetalPricesDTO) |
| GET | /api/pages/currency | Валюты (CurrenciesDTO) |

Без авторизации или с ней — уточняется в Swagger (иконка замка у эндпоинта).

---

## 6. Каталог (Catalog)

| Метод | Путь | Описание |
|-------|------|----------|
| GET  | /api/catalog/categories | Категории (CatalogCategoryListResponseDTO) |
| POST | /api/catalog/products | Список товаров с фильтрами (CatalogProductFilterRequestDTO → CatalogProductListResponseDTO) |
| GET  | /api/catalog/products/{id} | Товар по ID (CatalogProductResponseDTO) |
| POST | /api/catalog/filters | Варианты фильтров (ProductFilterOptionsResponse) |
| GET  | /api/catalog/filials | Филиалы (FilialListResponseDTO) |

---

## 7. Обратная связь (Feedback)

| Метод | Путь | Описание |
|-------|------|----------|
| POST | /api/feedback/create | Создать обращение |
| GET  | /api/feedback/{id} | Получить обращение по ID |
| GET  | /api/feedback/list/{page} | Список обращений (пагинация) |
| GET  | /api/feedback/statistics | Статистика по обращениям |

---

## Схемы (Schemas) в Swagger

- **Регистрация пользователя** — тело запроса регистрации.
- **Создание кода для подтверждения номера телефона** — тело/ответ для phone_checker/create.
- **UserDTO** — профиль пользователя.
- **UserOnesGetPledgeResponseDTO**, **UserOnesPledgeDTO**, **UserOnesPledgeDepositThingDTO** — залог и вещи.
- **PaymentInitResponseDTO** — ответ инициации оплаты.
- **MetalPricesDTO**, **MetalPriceDTO**, **CurrenciesDTO**, **CurrencyDTO** — металлы и валюты.
- **CatalogCategoryListResponseDTO**, **CatalogCategoryResponseDTO** — категории.
- **CatalogProductListResponseDTO**, **CatalogProductResponseDTO**, **CatalogProductPropertyResponseDTO** — товары.
- **CatalogProductFilterRequestDTO**, **ProductFilterOptionsResponse** — фильтры каталога.
- **FilialListResponseDTO**, **FilialResponseDTO**, **CatalogFilialResponseDTO** — филиалы.

---

## Кратко по сценариям

1. **Вход по номеру телефона**  
   `POST /api/auth/token/login` с телом `{"phone": "79998887766"}` → получить токен → использовать в заголовке Authorize для остальных запросов.

2. **Проверка/регистрация номера**  
   `POST /api/phone_checker/create` (тело по схеме в доке) → код подтверждения на телефон → далее регистрация/логин по документации.

3. **Данные текущего пользователя**  
   `GET /api/users/me` с токеном → UserDTO.

4. **Залог пользователя**  
   `GET /api/users/me/pledge` с токеном → залоги и вещи.

5. **Каталог и филиалы**  
   Категории, товары, фильтры, филиалы — через раздел Catalog.

6. **Оплата**  
   Инициация: `POST /api/payment/init`; уведомления от платёжки: `POST /api/payment/notification`.

Точные форматы полей запросов/ответов смотри в http://5.128.186.3/api/doc (вкладки Example Value и Schema у каждого эндпоинта и в блоке Schemas).

---

## API 1С LMB (отдельный сервис)

Базовый URL: `http://5.128.186.3/lmb/hs/es`.  
**Авторизация:** Basic Auth (логин `UserWebServis`, пароль `UserWebServis`).  
**Сессия:** если 1С требует сначала залогиниться, укажите в `.env` адрес страницы входа — перед каждым запросом будет выполняться POST на этот URL с Basic Auth, затем запрос к API идёт с полученной Cookie.

Переменные окружения:
- `LMB_USER_API_URL` — базовый URL (по умолчанию `http://5.128.186.3/lmb/hs/es`)
- `LMB_USER_API_LOGIN_URL` — URL для логина (если нужен отдельный шаг входа), например `http://5.128.186.3/lmb/hs/es/login`
- `LMB_USER_API_USERNAME`, `LMB_USER_API_PASSWORD` — учётные данные (UserWebServis / UserWebServis)

---

### 7. Выгрузка данных по контрагенту

**GET** `/lmb/hs/user/{ID}`  
ID — номер телефона (только цифры).

**Ответ:** код в базе, имя, телефон.

**Команда проверки:** `php artisan lmb:user 79139122194`

---

### Выгрузка каталога товаров (остатки) на сайт

**GET** `/lmb/hs/ostatki`  
**Ответ:** JSON (структура по формату 1С).

**Команда проверки:** `php artisan lmb:ostatki` (краткий вывод) или `php artisan lmb:ostatki --json` (полный JSON).

**В коде:** `app(LmbUserApiService::class)->getOstatki()` → массив или null.

---

### 8. Создать нового контрагента

**POST** `/lmb/hs/user_no`  
**Content-Type:** application/json  
**Тело:** JSON с данными контрагента (поля по формату 1С: имя, телефон и т.д.).

**В коде:**
```php
$api = app(\App\Services\LmbUserApiService::class);
$result = $api->createUser([
    'name' => '...',
    'phone' => '79139122194',
    // остальные поля по документации 1С
]);
// $result — массив ответа API или null при ошибке
```
