<?php

use App\Http\Controllers\Api\ClientApiController;
use Illuminate\Support\Facades\Route;

// REST API для клиентов (можно защитить API-токеном)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientApiController::class);
});

// Публичное API для списка клиентов (если нужен доступ без авторизации для фронта)
Route::get('clients', [ClientApiController::class, 'index']);
Route::get('clients/{client}', [ClientApiController::class, 'show']);
