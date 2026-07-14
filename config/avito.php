<?php

return [
    'api_base' => env('AVITO_API_BASE', 'https://api.avito.ru'),

    /** Филиалы колл-центра → ключ в настройках avito_branches (JSON). */
    'branch_defaults' => [
        'gorsky' => [
            'label' => 'Горский',
            'store_hint' => 'Горский',
        ],
        'michurina' => [
            'label' => 'Мичурина',
            'store_hint' => 'Мичурина',
        ],
        'kolhidskaya' => [
            'label' => 'Колхидская',
            'store_hint' => 'Колхидская',
        ],
        'dusi_kovalchuk' => [
            'label' => 'Дуси Ковальчук',
            'store_hint' => 'Дуси Ковальчук',
        ],
        'stanislavskogo' => [
            'label' => 'Станиславского',
            'store_hint' => 'Станиславского',
        ],
    ],
];
