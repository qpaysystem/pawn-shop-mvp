# Синхронизация остатков из регистров накопления 1С

Остатки в 1С хранятся в **регистрах накопления** (таблицы `_accumrg*` в PostgreSQL). По движениям регистра считаются остатки (приход − расход по измерениям).

## 1. Найти регистры в БД 1С

```bash
# Список таблиц _accumrg* и структура
php artisan lmb:1c-balances-discovery

# С подсчётом записей (медленно)
php artisan lmb:1c-balances-discovery --count

# Одна таблица
php artisan lmb:1c-balances-discovery --table=_accumrg26200
```

В выводе будут колонки: обычно `_period`, `_recordkind` (1 = приход, -1 = расход), измерения `_fld*rref` (ссылки на склад, номенклатуру и т.д.), ресурсы `_fld*` (количество, сумма).

Пример: в базе LMB регистр **\_accumrg26227** содержит 736 записей и имеет `_recordkind` — подходит для пробной настройки (уточните по метаданным 1С, какие именно _fld* являются измерениями и ресурсом количества).

## 2. Настроить синхронизацию в .env

После того как по выводу discovery вы определили нужную таблицу и имена колонок:

```env
# Таблица регистра накопления (например остатки товаров на складе)
LMB_1C_BALANCES_REGISTER_TABLE=_accumrg26200

# Колонки измерений через запятую (по ним группируется остаток)
LMB_1C_BALANCES_DIMENSION_COLUMNS=_fld26201rref,_fld26202rref

# Колонка количества (ресурс)
LMB_1C_BALANCES_QUANTITY_COLUMN=_fld26205

# Колонка суммы (опционально)
LMB_1C_BALANCES_AMOUNT_COLUMN=

# Колонка вида движения (1 приход, -1 расход). По умолчанию _recordkind
LMB_1C_BALANCES_RECORDKIND_COLUMN=_recordkind
```

Конфиг: `config/services.php` → `lmb_1c_balances_sync`.

## 3. Запуск синхронизации

```bash
php artisan lmb:sync-1c-balances --dry-run   # проверить настройки
php artisan lmb:sync-1c-balances             # записать остатки в нашу БД
```

Результат пишется в таблицу **`lmb_register_balances`**: по каждому сочетанию измерений — одна строка (quantity, amount, raw_dimensions). Поля `store_id` и `item_id` можно заполнять отдельно (маппинг склада/номенклатуры 1С на наши справочники).

## 4. Использование в приложении

- Модель: `App\Models\LmbRegisterBalance`.
- Связи: `store_id`, `item_id` (опционально).
- Для отчётов и выгрузок можно выбирать по `register_name` и при необходимости джойнить с `stores`/`items` по маппингу из `raw_dimensions`.

## См. также

- `docs/LMB_1C_TABLES_STRUCTURE_AND_SYNC.md` — структура таблиц 1С
- `docs/1c_export_metadata_to_file.txt` — выгрузка метаданных из Конфигуратора для точного соответствия имён регистров
