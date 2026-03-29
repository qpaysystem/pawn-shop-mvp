<?php

return [
    'vapid' => [
        'public' => env('VAPID_PUBLIC_KEY', ''),
        'private' => env('VAPID_PRIVATE_KEY', ''),
    ],
    'lombard' => [
        'name' => env('LOMBARD_NAME', 'Ломбард'),
        'phone' => env('LOMBARD_PHONE', '+7 (383) 291-00-51'),
    ],
    'jitsi' => [
        // Базовый URL сервера Jitsi Meet (без завершающего слэша).
        // Публичный: https://meet.jit.si
        // Свой сервер: https://meet.ваш-домен.ru
        'server_url' => env('JITSI_SERVER_URL', 'https://meet.jit.si'),
    ],
    'google_vision' => [
        // API-ключ для Cloud Vision API. Получить: Google Cloud Console → APIs → Vision API → Credentials.
        'api_key' => env('GOOGLE_VISION_API_KEY', ''),
    ],
    'gemini' => [
        // Распознавание текста с фото через Google AI Studio (Gemini). Бесплатный tier. Ключ: https://aistudio.google.com/apikey
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
    'openai' => [
        // Паспорт (LLM), транскрипция звонков: Whisper + оформление текста (тот же ключ). Получить: https://platform.openai.com/api-keys
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'deepseek' => [
        // Распознавание и извлечение ФИО с фото паспорта (vision + структурированный вывод в одном запросе).
        // Получить ключ: https://platform.deepseek.com/api_keys
        'api_key' => env('DEEPSEEK_API_KEY', ''),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],
    'serper' => [
        // Поиск похожих объявлений Авито и картинок для AI-оценки. Бесплатно 2500 запросов/мес.
        // Ключ: https://serper.dev
        'api_key' => env('SERPER_API_KEY', ''),
    ],
    'mts_vpbx' => [
        // vpbx — ЛК vpbx.mts.ru (X-AUTH-TOKEN). ac20 — Автосекретарь 2.0 (Bearer JWT). auto — AC20, если заданы MTS_AC20_DOMAIN и MTS_AC20_TRUNK_ID (10 цифр), иначе VPBX
        'api' => strtolower((string) env('MTS_TELEPHONY_API', 'auto')),
        'url' => env('MTS_VPBX_URL', 'https://vpbx.mts.ru'),
        'login' => env('MTS_VPBX_LOGIN', ''),
        'password' => env('MTS_VPBX_PASSWORD', ''),
        'ac20_base_url' => rtrim(env('MTS_AC20_BASE_URL', 'https://aa.mts.ru/api/ac20'), '/'),
        'ac20_domain' => env('MTS_AC20_DOMAIN', ''),
        'ac20_trunk_id' => preg_replace('/\D/', '', (string) env('MTS_AC20_TRUNK_ID', '')),
    ],
    // Выгрузка данных по контрагенту (1С). GET {base_url}/user/{phone} → JSON (user_uid, first_name, …) или {}. Basic Auth: UserWebServis / UserWebServis
    'lmb_user_api' => [
        'base_url' => env('LMB_USER_API_URL', 'http://5.128.186.3/lmb/hs/es'),
        'login_url' => env('LMB_USER_API_LOGIN_URL', ''), // если задан — сначала POST сюда, затем запросы с Cookie
        'timeout' => (int) env('LMB_USER_API_TIMEOUT', 8),
        'username' => env('LMB_USER_API_USERNAME', 'UserWebServis'),
        'password' => env('LMB_USER_API_PASSWORD', 'UserWebServis'),
    ],
    // Пользователь 1С (логин в конфигурацию) — для веб-сервисов/интеграций, требующих авторизацию пользователя 1С
    'lmb_1c_user' => [
        'username' => env('LMB_1C_USER', ''),
        'password' => env('LMB_1C_PASSWORD', ''),
    ],
    // Синхронизация филиалов/подразделений из 1С в stores (для маппинга по lmb_store_uid при переносе залогов/скупки).
    'lmb_1c_stores_sync' => [
        'table' => env('LMB_1C_STORES_REFERENCE_TABLE', '_reference197'),
        'name_column' => env('LMB_1C_STORES_NAME_COLUMN', '_description'),
        'address_column' => env('LMB_1C_STORES_ADDRESS_COLUMN', '_fld5205'), // опционально
    ],
    // Синхронизация контрагентов из БД 1С в таблицу clients. Таблицу контрагентов уточнить по метаданным 1С (см. docs/LMB_ZALOGODATELI_export.md).
    'lmb_1c_contragent_sync' => [
        'table' => env('LMB_1C_CONTRAGENT_TABLE', '_reference122x1'), // физлица: ФИО, телефон, паспорт
        'phone_column' => env('LMB_1C_CONTRAGENT_PHONE_COLUMN', '_fld41084'),
        'name_column' => env('LMB_1C_CONTRAGENT_NAME_COLUMN', '_fld3178'), // ФИО (если пусто — _description)
        'passport_series_column' => env('LMB_1C_CONTRAGENT_PASSPORT_SERIES_COLUMN', '_fld3202'), // серия паспорта (в этой базе ~23k заполнено)
        'passport_number_column' => env('LMB_1C_CONTRAGENT_PASSPORT_NUMBER_COLUMN', '_fld3201'), // номер паспорта
        'date_created_column' => env('LMB_1C_CONTRAGENT_DATE_CREATED_COLUMN', '_fld3191'), // дата создания в 1С → clients.lmb_created_at
        'traffic_source_ref_column' => env('LMB_1C_CONTRAGENT_TRAFFIC_SOURCE_COLUMN', '_fld41085rref'), // ссылка на справочник «Источник рекламы»
        'traffic_source_ref_table' => env('LMB_1C_TRAFFIC_SOURCE_REF_TABLE', ''), // таблица справочника «Источник рекламы» (1С: РС_ИсточникРекламы), напр. _referenceXXX; см. docs/LMB_1C_METADATA_LOMBARD.md
        'date_issued_column' => env('LMB_1C_CONTRAGENT_DATE_ISSUED_COLUMN', ''), // дата выдачи документа (когда выдан), напр. _fld3192
        'document_type_column' => env('LMB_1C_CONTRAGENT_DOCUMENT_TYPE_COLUMN', ''), // вид документа удостоверяющего личность в ref122, напр. _fld3196; иначе подтягивается из vt3220
        // Регистр сведений 1С с серией/номером паспорта по ссылке на контрагента (если в ref122 поля пустые — см. docs)
        'inforeg_passport_table' => env('LMB_1C_INFOREG_PASSPORT_TABLE', '_inforg25994'),
        'inforeg_passport_period_column' => env('LMB_1C_INFOREG_PASSPORT_PERIOD_COLUMN', '_period'),
        'inforeg_passport_contragent_ref_column' => env('LMB_1C_INFOREG_PASSPORT_CONTRAGENT_COLUMN', '_fld25995rref'),
        'inforeg_passport_series_column' => env('LMB_1C_INFOREG_PASSPORT_SERIES_COLUMN', '_fld26001'),
        'inforeg_passport_number_column' => env('LMB_1C_INFOREG_PASSPORT_NUMBER_COLUMN', '_fld26002'),
        // Адрес регистрации: табличная часть контактов контрагента
        'vt3220_table' => env('LMB_1C_VT3220_TABLE', '_reference122_vt3220x1'),
        'vt3220_contragent_ref_column' => env('LMB_1C_VT3220_CONTRAGENT_COLUMN', '_reference122_idrref'),
        'vt3220_address_line_column' => env('LMB_1C_VT3220_ADDRESS_LINE_COLUMN', '_fld3224'),
        // Через запятую: прописка часто в _fld3225/_fld3227/_fld34009, а не только в _fld3224 (склеиваются через CONCAT_WS)
        'vt3220_address_line_columns' => env('LMB_1C_VT3220_ADDRESS_LINE_COLUMNS', '_fld3224,_fld3225,_fld3227,_fld34009'),
        // Текст «паспорт / кем выдан / дата» из документа операций (имя и колонки — по метаданным Ломбард)
        'identity_document_table' => env('LMB_1C_IDENTITY_DOCUMENT_TABLE', '_document517x1'),
        'identity_document_fio_column' => env('LMB_1C_IDENTITY_DOCUMENT_FIO_COLUMN', '_fld15354'),
        'identity_document_passport_text_column' => env('LMB_1C_IDENTITY_DOCUMENT_PASSPORT_TEXT_COLUMN', '_fld15357'),
        'identity_document_date_column' => env('LMB_1C_IDENTITY_DOCUMENT_DATE_COLUMN', '_date_time'),
        // При полном синке десятков тыс. контрагентов JOIN ref122+vt3220 по списку ФИО раздувает память — резерв отключается выше лимита
        'identity_preload_max_fio_for_address_by_fio' => (int) env('LMB_1C_IDENTITY_MAX_FIO_ADDRESS_BY_FIO', 600),
        // Тексты из док. 517 по ФИО: при > лимита не грузим в preload (дозаполнение: lmb:sync-contragents --backfill-identity-from-1c или syncIdentityDetailsFrom1cForExistingClients)
        'identity_preload_max_fio_for_narratives' => (int) env('LMB_1C_IDENTITY_MAX_FIO_NARRATIVES', 2000),
    ],
    // Синхронизация действующих залогов из 1С: документ залога (ЛМБ_ОперацияПоЗалогу) + табличная часть. В части баз таблица документа — _document31784 без x1 (lmb:db-schema при --table=_document31784x1 подставит _document31784).
    'lmb_1c_pawn_sync' => [
        'document_table' => env('LMB_1C_PAWN_DOCUMENT_TABLE', ''), // _document31784 или _document31784x1
        'contragent_column' => env('LMB_1C_PAWN_CONTRAGENT_COLUMN', '_fld31884rref'), // контрагент (по структуре Ломбард 4.0)
        'date_column' => env('LMB_1C_PAWN_DATE_COLUMN', '_date_time'),
        'number_column' => env('LMB_1C_PAWN_NUMBER_COLUMN', '_number'),
        'amount_column' => env('LMB_1C_PAWN_AMOUNT_COLUMN', '_fld31887'), // сумма займа (уточнить по метаданным)
        'percent_column' => env('LMB_1C_PAWN_PERCENT_COLUMN', '_fld31888'),
        'expiry_column' => env('LMB_1C_PAWN_EXPIRY_COLUMN', ''), // дата окончания — уточнить по _document31784
        'buyback_amount_column' => env('LMB_1C_PAWN_BUYBACK_AMOUNT_COLUMN', ''),
        'table_part_table' => env('LMB_1C_PAWN_TABLE_PART_TABLE', '_document31784_vt31892'), // вещи в залоге
        'vt_doc_id_column' => env('LMB_1C_PAWN_VT_DOC_ID_COLUMN', '_document31784_idrref'),
        'vt_nomenclature_column' => env('LMB_1C_PAWN_VT_NOMENCLATURE_COLUMN', '_fld31894rref'),
        'vt_amount_column' => env('LMB_1C_PAWN_VT_AMOUNT_COLUMN', '_fld31896'),
        'vt_description_column' => env('LMB_1C_PAWN_VT_DESCRIPTION_COLUMN', '_fld31895'), // наименование в строке (mvarchar 50)
        'default_store_id' => (int) env('LMB_1C_PAWN_DEFAULT_STORE_ID', 1),
        'only_acting' => env('LMB_1C_PAWN_ONLY_ACTING', true),
        'create_placeholder_client_for_unknown' => env('LMB_1C_PAWN_CREATE_PLACEHOLDER_CLIENT', false), // если true — при отсутствии контрагента в нашей базе создать клиента «Клиент 1С (залог)» и перенести залог
        'store_column' => env('LMB_1C_PAWN_STORE_COLUMN', ''), // реквизит документа — ссылка на склад/филиал (например _fld9450rref); при наличии ищется Store по lmb_store_uid = encode(ref,'hex') или по store_mapping
        'store_mapping' => json_decode(env('LMB_1C_PAWN_STORE_MAPPING', '{}'), true) ?: [], // доп. маппинг hex_uid_1c => store_id (если stores.lmb_store_uid не заполнен)
        // Только договоры с положительным остатком в регистре накопления (ЛМБ: _accumrg26227 + _reference252x1 → номер документа). См. lmb:sync-pawn-contracts --with-register-balance
        'filter_by_balance_register' => filter_var(env('LMB_1C_PAWN_FILTER_BY_BALANCE_REGISTER', false), FILTER_VALIDATE_BOOL),
        'balance_register' => [
            'register_table' => env('LMB_1C_PAWN_BALANCE_REGISTER_TABLE', '_accumrg26227'),
            'resource_column' => env('LMB_1C_PAWN_BALANCE_RESOURCE_COLUMN', '_fld26234'),
            'ref252_table' => env('LMB_1C_PAWN_BALANCE_REF252_TABLE', '_reference252x1'),
            'register_ref252_column' => env('LMB_1C_PAWN_BALANCE_REGISTER_REF252_COLUMN', '_fld26232rref'),
            // Номер документа: lpad(последний сегмент _code справочника 252 по «-», 9, «0») = документ._number
            'match_doc_number_pad' => (int) env('LMB_1C_PAWN_BALANCE_NUMBER_PAD', 9),
        ],
    ],
    // Синхронизация договоров скупки из 1С (_document389x1): создаёт PurchaseContract + Item.
    'lmb_1c_purchase_sync' => [
        'document_table' => env('LMB_1C_PURCHASE_DOCUMENT_TABLE', '_document389x1'),
        'contragent_column' => env('LMB_1C_PURCHASE_CONTRAGENT_COLUMN', '_fld9626rref'),
        'date_column' => env('LMB_1C_PURCHASE_DATE_COLUMN', '_date_time'),
        'number_column' => env('LMB_1C_PURCHASE_NUMBER_COLUMN', '_number'),
        'amount_column' => env('LMB_1C_PURCHASE_AMOUNT_COLUMN', '_fld9631'),
        'name_columns' => array_filter(array_map('trim', explode(',', env('LMB_1C_PURCHASE_NAME_COLUMNS', '_fld9638,_fld9643,_fld9650')))),
        'default_store_id' => (int) env('LMB_1C_PURCHASE_DEFAULT_STORE_ID', 1),
        'store_column' => env('LMB_1C_PURCHASE_STORE_COLUMN', '_fld9627rref'), // склад в документе скупки (43 разных значения в 1С)
        'create_missing_clients' => filter_var(env('LMB_1C_PURCHASE_CREATE_CLIENTS', true), FILTER_VALIDATE_BOOL),
        'skip_zero_amount' => filter_var(env('LMB_1C_PURCHASE_SKIP_ZERO_AMOUNT', true), FILTER_VALIDATE_BOOL),
    ],
    // Выгрузка скупки в CSV/JSON (lmb:export-purchases-from-1c): JOIN номенклатуры и вложений 1С.
    'lmb_1c_purchase_export' => [
        'nomenclature_table' => env('LMB_1C_PURCHASE_EXPORT_NOMENCLATURE_TABLE', '_reference45'),
        'nomenclature_ref_column' => env('LMB_1C_PURCHASE_EXPORT_NOMENCLATURE_REF_COLUMN', '_fld9632rref'),
        'attachment_table' => env('LMB_1C_PURCHASE_EXPORT_ATTACHMENT_TABLE', '_reference367x1'),
        'attachment_ref_column' => env('LMB_1C_PURCHASE_EXPORT_ATTACHMENT_REF_COLUMN', '_fld46109rref'),
        'attachment2_ref_column' => env('LMB_1C_PURCHASE_EXPORT_ATTACHMENT2_REF_COLUMN', '_fld46110rref'),
        'responsible_ref_column' => env('LMB_1C_PURCHASE_EXPORT_RESPONSIBLE_REF_COLUMN', '_fld9620rref'),
        // Доп. ссылка на подразделение/витрину в документе скупки (если склад _fld9627 пустой).
        'branch_alt_table' => env('LMB_1C_PURCHASE_EXPORT_BRANCH_ALT_TABLE', '_reference201x1'),
        'branch_alt_ref_column' => env('LMB_1C_PURCHASE_EXPORT_BRANCH_ALT_REF_COLUMN', '_fld9644rref'),
    ],
    // Синхронизация остатков из регистров накопления 1С (_accumrg*). Укажите таблицу и колонки после lmb:1c-balances-discovery.
    'lmb_1c_balances_sync' => [
        'register_table' => env('LMB_1C_BALANCES_REGISTER_TABLE', ''),
        'dimension_columns' => array_filter(array_map('trim', explode(',', env('LMB_1C_BALANCES_DIMENSION_COLUMNS', '')))),
        'quantity_column' => env('LMB_1C_BALANCES_QUANTITY_COLUMN', ''),
        'amount_column' => env('LMB_1C_BALANCES_AMOUNT_COLUMN', ''),
        'recordkind_column' => env('LMB_1C_BALANCES_RECORDKIND_COLUMN', '_recordkind'),
    ],
    // Документ скупки в 1С (_document389x1): колонки для наименования товара (по порядку, первая непустая — имя позиции)
    'lmb_1c_purchase_item_name' => [
        'document_table' => env('LMB_1C_PURCHASE_DOCUMENT_TABLE', '_document389x1'),
        'name_columns' => array_filter(array_map('trim', explode(',', env('LMB_1C_PURCHASE_NAME_COLUMNS', '_fld9638,_fld9643,_fld9650')))),
    ],
    // 2ГИС: данные карточки организации (рейтинг, отзывы). Ключ — Platform Manager. ID филиалов — из ссылки 2gis.ru/.../firm/ID или через запятую в DGIS_BRANCH_IDS.
    'dgis' => [
        'api_key' => env('DGIS_API_KEY', ''),
        'branch_id' => env('DGIS_BRANCH_ID', ''),
        'branch_ids' => array_filter(array_map('trim', explode(',', env('DGIS_BRANCH_IDS', env('DGIS_BRANCH_ID', ''))))),
        'api_url' => 'https://catalog.api.2gis.com/3.0/items/byid',
    ],
];
