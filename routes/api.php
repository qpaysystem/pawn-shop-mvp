<?php

use App\Http\Controllers\Api\ClientApiController;
use Illuminate\Support\Facades\Route;

// REST API для клиентов (можно защитить API-токеном). Префикс имён api. — чтобы не конфликтовать с web-маршрутами clients.*
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientApiController::class)->names('api.clients');
});

// Публичное API для списка клиентов (если нужен доступ без авторизации для фронта)
Route::get('clients', [ClientApiController::class, 'index'])->name('api.clients.index.public');
Route::get('clients/{client}', [ClientApiController::class, 'show'])->name('api.clients.show.public');
