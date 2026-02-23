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
        // API-ключ для извлечения полей из текста паспорта через LLM. Получить: https://platform.openai.com/api-keys
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
        // API CRM MTS VPBX: токен из раздела «Методы API» в ЛК, передаётся в заголовке X-AUTH-TOKEN
        'url' => env('MTS_VPBX_URL', 'https://vpbx.mts.ru'),
        'login' => env('MTS_VPBX_LOGIN', ''),
        'password' => env('MTS_VPBX_PASSWORD', ''), // токен авторизации (X-AUTH-TOKEN)
    ],
    // Выгрузка данных по контрагенту (1С). Сначала логин (если указан login_url), затем запросы с сессией. Basic Auth: UserWebServis / UserWebServis
    'lmb_user_api' => [
        'base_url' => env('LMB_USER_API_URL', 'http://5.128.186.3:5665/lmb/hs/es'),
        'login_url' => env('LMB_USER_API_LOGIN_URL', ''), // если задан — сначала POST сюда, затем запросы с Cookie
        'timeout' => (int) env('LMB_USER_API_TIMEOUT', 8),
        'username' => env('LMB_USER_API_USERNAME', 'UserWebServis'),
        'password' => env('LMB_USER_API_PASSWORD', 'UserWebServis'),
    ],
];
