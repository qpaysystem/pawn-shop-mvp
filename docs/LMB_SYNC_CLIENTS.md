# Синхронизация клиентов из базы 1С в раздел «Клиенты»

Контрагенты из БД 1С (PostgreSQL) переносятся в таблицу `clients` приложения — они отображаются в разделе **Клиенты** вместе с локально созданными.

## Как запустить

1. В `.env` должны быть заданы подключение к БД 1С и таблица контрагентов:
   - `LMB_DB_DRIVER=pgsql`, `LMB_DB_HOST`, `LMB_DB_DATABASE`, `LMB_DB_USERNAME`, `LMB_DB_PASSWORD`
   - `LMB_1C_CONTRAGENT_TABLE` — имя таблицы справочника контрагентов (например `_reference108`)

2. Выполнить синхронизацию:
   ```bash
   php artisan lmb:sync-contragents
   ```

3. Сухой прогон (без записи в БД):
   ```bash
   php artisan lmb:sync-contragents --dry-run
   ```

После синхронизации все загруженные контрагенты видны в разделе **Клиенты** (маршрут `/clients`).

### Дозаполнение паспорта и реквизитов удостоверения личности из 1С

- **Серия/номер из регистра (inforeg)** — после основного синка:
  ```bash
  php artisan lmb:sync-contragents --backfill-passports-from-inforeg
  ```
- **Вид документа, кем/когда выдан, адрес регистрации** (таблица документа 517 + `vt3220` в PostgreSQL, см. `config/services.php` → `lmb_1c_contragent_sync`):
  ```bash
  php artisan lmb:sync-contragents --backfill-identity-from-1c
  ```
- Отдельно только паспорт из inforeg, затем сразу реквизиты документа:
  ```bash
  php artisan lmb:sync-passports-from-1c --with-identity
  ```

Перед первым использованием реквизитов выполните миграции: `php artisan migrate` (поля `lmb_identity_document_type`, `lmb_passport_issued_by`, `lmb_passport_issued_at`, `lmb_registration_address` в `clients`).

Адрес регистрации из `vt3220` склеивается из нескольких колонок (по умолчанию `_fld3224,_fld3225,_fld3227,_fld34009`). Если в вашей БД нет какой‑то колонки — задайте в `.env` только существующие: `LMB_1C_VT3220_ADDRESS_LINE_COLUMNS=_fld3224,_fld3225`.

При **очень большом** числе контрагентов (> ~2000 уникальных ФИО) при полном синке не подгружаются тексты из документа 517 в один проход (экономия памяти). Адреса по UID из `vt3220` подтягиваются как раньше. Дозаполнение реквизитов из док. 517: `php artisan lmb:sync-contragents --backfill-identity-from-1c` (батчами). Лимиты: `LMB_1C_IDENTITY_MAX_FIO_NARRATIVES`, `LMB_1C_IDENTITY_MAX_FIO_ADDRESS_BY_FIO`. Команда `lmb:sync-contragents` поднимает лимит PHP-памяти до 512M на время выполнения.

## Как указать таблицу контрагентов в 1С

В 1С контрагенты хранятся в справочнике (например, «Контрагенты»). В PostgreSQL ему соответствует таблица вида `public._referenceNNN` (NNN — внутренний ID объекта в метаданных).

- Узнать точное имя таблицы можно:
  1. По выгрузке метаданных из Конфигуратора 1С (см. `docs/1c_export_metadata_to_file.txt`) — по синониму справочника и списку таблиц в БД.
  2. Через просмотр структуры БД: `php artisan lmb:db-schema --limit=50` и поиск таблиц с полями `_description`, `_code`, полем телефона (часто `_fldXXXX`).

- В `.env` задать:
  - `LMB_1C_CONTRAGENT_TABLE=_reference108` (подставьте нужное имя)
  - `LMB_1C_CONTRAGENT_PHONE_COLUMN=_fld2988` (колонка с телефоном; если не задана, в качестве «телефона» для уникальности подставляется `1C-{user_uid}`)

По умолчанию в конфиге указана таблица `_reference108` (в вашей базе это банки/организации). Для справочника **физлиц** (залогодателей) нужно подставить таблицу, соответствующую этому справочнику в метаданных 1С.
