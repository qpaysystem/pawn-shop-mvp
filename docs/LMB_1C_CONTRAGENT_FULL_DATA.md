# Залогодатель = контрагент: как собрать данные из PostgreSQL (1С)

Один физический контрагент в ломбарде — это элемент справочника **Контрагенты**. В вашей базе основная таблица расширения: **`public._reference122x1`**. Часть полей (паспорт, «кем выдан», вид документа) дублируется или полностью лежит в **хранилище значений** **`public._reference122_vt3220x1`** (тип «Документ удостоверяющий личность» + контакты).

---

## 1. Сводка: что где лежит

| Нужные данные | Где в PostgreSQL | Колонки / примечание |
|---------------|------------------|----------------------|
| **ФИО** | `_reference122x1` | `_fld3178` (полное ФИО), при пустом — `_description` |
| **Телефон** | `_reference122x1` | `_fld41084` |
| **Серия / номер паспорта** | `_reference122x1` | Основные: `_fld3202` (серия), `_fld3201` (номер). Альтернатива: `_fld3184`, `_fld3185` |
| **Кем выдан, дата выдачи** | `_reference122x1` + `vt3220` | «Кем выдан»: `_fld3197` (часто пусто). Дата выдачи — если есть отдельное поле в вашей базе, уточнить по `lmb:db-schema --table=_reference122x1` или задать `LMB_1C_CONTRAGENT_DATE_ISSUED_COLUMN`. Дополнительно строки в **`_reference122_vt3220x1`**: `_fld3224`, `_fld3225`, `_fld3227`, `_fld34009` (тексты, в т.ч. МВД/«выдан») |
| **Вид документа удостоверяющего личность** | **`_reference122_vt3220x1`** | Ссылка: **`_fld3222rref`** → в этой базе совпадает с **`_idrref`** в **`public._enum1039`**. **Текста «Паспорт гражданина РФ» в PostgreSQL нет** — только `encode(_fld3222rref,'hex')` и порядок в `_enum1039._enumorder`. Расшифровка — из Конфигуратора 1С / метаданных |
| **Прописка / адрес регистрации** | Уточняется по схеме | В карточке часто «юридический адрес» / адрес регистрации — это отдельные реквизиты. Выполните **`php artisan lmb:db-schema --table=_reference122x1`** и найдите длинные `mvarchar` / поля с адресом (номер `_fld*` разный в конфигурациях). Часть адресов может быть в **`_reference122_vt3220x1`** в строках контактной информации (не только паспорт) |
| **Ключ связи** | Везде | `encode(c._idrref, 'hex')` — UID контрагента; в vt3220: **`_reference122_idrref`** = `c._idrref` |

**Важно:** у части контрагентов паспорт на форме 1С заполнен, а в `_fld3201`/`_fld3202` пусто — тогда данные могут быть только в **`vt3220`** (см. `lmb:export-passport-from-1c --source=vt3220 --uid=HEX`).

---

## 2. Один запрос: основная карточка + вид документа (ссылка) + одна строка из vt3220

Ниже шаблон: подставьте имена колонок адреса/даты выдачи после просмотра схемы.

```sql
SELECT
    encode(c._idrref, 'hex') AS contragent_uid,
    trim(c."_fld3178"::text) AS fio,
    trim(c."_description"::text) AS name_short,
    trim(c."_fld41084"::text) AS phone,

    -- Паспорт (основные поля в ref122)
    trim(c."_fld3202"::text) AS passport_series,
    trim(c."_fld3201"::text) AS passport_number,
    trim(c."_fld3184"::text) AS passport_series_alt,
    trim(c."_fld3185"::text) AS passport_number_alt,
    trim(c."_fld3197"::text) AS issued_by_text,

    -- Вид документа: только идентификатор перечисления в hex (текста в БД нет)
    (
        SELECT encode(vt."_fld3222rref", 'hex')
        FROM public._reference122_vt3220x1 vt
        WHERE vt."_reference122_idrref" = c._idrref
          AND vt."_fld3222rref" IS NOT NULL
          AND octet_length(vt."_fld3222rref") > 0
        ORDER BY vt."_lineno3221" NULLS LAST
        LIMIT 1
    ) AS document_type_enum_uid_hex,

    -- Пример строк из хранилища (могут быть телефон/адрес/паспорт — фильтруйте по смыслу)
    (
        SELECT trim(vt."_fld3224"::text)
        FROM public._reference122_vt3220x1 vt
        WHERE vt."_reference122_idrref" = c._idrref
        ORDER BY vt."_lineno3221" NULLS LAST
        LIMIT 1
    ) AS vt3220_fld3224_sample

FROM public._reference122x1 c
WHERE NOT COALESCE(c._marked, false);
```

Для **полной** картины по паспорту по одному UID:

```bash
php artisan lmb:export-passport-from-1c --uid=<HEX> --source=both
```

---

## 3. Прописка (адрес регистрации)

1. **`php artisan lmb:db-schema --table=_reference122x1`** — просмотреть все `_fld*`; адрес часто в полях с большой длиной (`mvarchar(250)` и т.п.).
2. **`php artisan lmb:export-passport-from-1c --source=vt3220 --limit=50`** — посмотреть, в каких колонках `vt3220` встречаются подстроки «обл», «ул», индекс.
3. При наличии доступа к **Конфигуратору 1С** — открыть справочник Контрагенты и посмотреть **имя реквизита** адреса регистрации → по выгрузке метаданных сопоставить с `_fldNNNN`.

После определения колонки добавьте её в SELECT и при необходимости в `.env` (отдельный ключ в `config/services.php` под синхронизацию).

---

## 4. Сборка в приложении (Laravel)

Уже используется:

- **`LmbContragentSyncService`** — ФИО, телефон, серия/номер из `_reference122x1` (настройки `lmb_1c_contragent_sync` в `config/services.php`).
- **Регистр сведений с паспортом** — если в `ref122` поля `_fld3202`/`_fld3201` пустые, серия и номер подставляются из регистра (в вашей базе: **`public._inforg25994`**: ссылка на контрагента **`_fld25995rref`**, серия **`_fld26001`**, номер **`_fld26002`**, берётся последняя запись по **`_period`**). Настройки: `LMB_1C_INFOREG_PASSPORT_TABLE` и связанные ключи в `config/services.php`.
- Команды:
  - `php artisan lmb:sync-contragents` — при синке контрагентов паспорт объединяется (ref → иначе inforeg).
  - `php artisan lmb:sync-contragents --backfill-passports-from-inforeg` — после полного синка дозаполнить пустые `passport_data` из регистра.
  - `php artisan lmb:sync-passports-from-1c` — только дозаполнение паспортов по уже импортированным клиентам (`--force` перезаписывает существующий текст).
- Расширения: **`LMB_1C_CONTRAGENT_DATE_ISSUED_COLUMN`**, **`LMB_1C_CONTRAGENT_DOCUMENT_TYPE_COLUMN`** — если найдёте физические колонки в `ref122`.

Для **вида документа** в UI можно хранить **`document_type_enum_uid_hex`** (как выше) и маппить на названия вручную / из выгрузки 1С; либо получать текст через **веб-сервис 1С**, если он отдаёт расшифровку.

---

## 5. Команды-напоминания

| Задача | Команда |
|--------|---------|
| Структура таблицы контрагента | `php artisan lmb:db-schema --table=_reference122x1` |
| Структура хранилища документа | `php artisan lmb:db-schema --table=_reference122_vt3220x1` |
| Куда ссылается вид документа (`_fld3222rref`) | `php artisan lmb:doc-identity-rref-lookup` |
| Выгрузка паспорта по одному / списку | `php artisan lmb:export-passport-from-1c --uid=…` / `--list=…` |
| Синк клиентов в БД приложения | `php artisan lmb:sync-contragents` |

---

## 6. Связанные документы

- **docs/LMB_1C_TABLES_STRUCTURE_AND_SYNC.md** — колонки контрагента и синхронизация.
- **docs/LMB_1C_METADATA_LOMBARD.md** — раздел 6–7 (форма контрагента, документ удостоверяющий личность).
- **docs/LMB_1C_PASSPORT_TABLES_SEARCH.md** — поиск паспортных полей.
- **docs/LMB_1C_DOCUMENT_TYPES_AND_PASSPORT_FILE.md** — **все варианты вида документа** (`_enum1039`), **файл паспорта** (`_fld41630rref` → `_reference362x1`).
