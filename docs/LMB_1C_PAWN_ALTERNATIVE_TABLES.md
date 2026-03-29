# Альтернативные таблицы документа залога в 1С

Пресеты .env для переноса залогов из таблиц **_document41694x1** (1245 записей) и **_document41615x1** (321 813 записей). Подставьте блок в .env и выполните `php artisan config:clear && php artisan lmb:sync-pawn-contracts --all --force`.

**Проверено:** перенос из _document41694x1 — **978 договоров создано**, 267 пропущено (сумма 0 или некорректные данные). В .env переключено на эту таблицу.

---

## 1. _document41694x1 (1245 документов)

Табличной части нет — один документ = один договор залога. Контрагент: _fld41695rref, сумма: _fld41697.

```env
# Залог из _document41694x1 (без табличной части)
LMB_1C_PAWN_DOCUMENT_TABLE=_document41694x1
LMB_1C_PAWN_CONTRAGENT_COLUMN=_fld41695rref
LMB_1C_PAWN_DATE_COLUMN=_date_time
LMB_1C_PAWN_NUMBER_COLUMN=_number
LMB_1C_PAWN_AMOUNT_COLUMN=_fld41697
LMB_1C_PAWN_EXPIRY_COLUMN=
LMB_1C_PAWN_TABLE_PART_TABLE=
LMB_1C_PAWN_VT_DOC_ID_COLUMN=
LMB_1C_PAWN_VT_NOMENCLATURE_COLUMN=
LMB_1C_PAWN_VT_AMOUNT_COLUMN=
LMB_1C_PAWN_VT_DESCRIPTION_COLUMN=
```

Если не задана табличная часть, остальные VT_* могут быть пустыми; в коде используется пустая строка.

---

## 2. _document41615x1 (321 813 документов)

Есть табличные части (_document41615_vt41687x1, _vt46142x1 и др.). В выборке встречается тип «Перемещение» (_fld41617) — возможно, это не только залоги. Контрагент — вероятно _fld41618rref или _fld41620rref. Сумма и табличная часть с вещами — уточнить по выгрузке метаданных 1С. Пресет пока не проверялся.

После уточнения колонок задать в .env таблицу, контрагент, дату, номер, сумму, при необходимости ТЧ (имя таблицы и _document41615_idrref для связи). Табличные части: `php artisan lmb:db-schema --table=_document41615_vt46142x1` и др.

---

## 3. Вернуться к _document382 (4 документа)

```env
LMB_1C_PAWN_DOCUMENT_TABLE=_document382
LMB_1C_PAWN_CONTRAGENT_COLUMN=_fld9447rref
LMB_1C_PAWN_DATE_COLUMN=_date_time
LMB_1C_PAWN_NUMBER_COLUMN=_number
LMB_1C_PAWN_AMOUNT_COLUMN=_fld9452
LMB_1C_PAWN_EXPIRY_COLUMN=_fld33768
LMB_1C_PAWN_TABLE_PART_TABLE=_document382_vt9463
LMB_1C_PAWN_VT_DOC_ID_COLUMN=_document382_idrref
LMB_1C_PAWN_VT_NOMENCLATURE_COLUMN=_fld9466rref
LMB_1C_PAWN_VT_AMOUNT_COLUMN=_fld40394
LMB_1C_PAWN_VT_DESCRIPTION_COLUMN=_fld9467
```
