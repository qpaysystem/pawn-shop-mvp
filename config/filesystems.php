<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        // Фото товаров (симлинк: public/storage -> storage/app/public)
        'items' => [
            'driver' => 'local',
            'root' => storage_path('app/public/items'),
            'url' => env('APP_URL').'/storage/items',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
];
