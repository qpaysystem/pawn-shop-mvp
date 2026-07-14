<?php

use App\Http\Controllers\Api\AgentTeamsMtsController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClientController as V1ClientController;
use App\Http\Controllers\Api\V1\PawnContractController as V1PawnContractController;
use App\Http\Controllers\Api\V1\ReportController as V1ReportController;
use App\Http\Controllers\AcceptItemController;
use Illuminate\Support\Facades\Route;

// Mobile API v1 (Sanctum bearer tokens; web session login unchanged).
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::get('stores', [CatalogController::class, 'stores'])->name('api.v1.stores');
        Route::get('item-categories', [CatalogController::class, 'itemCategories'])->name('api.v1.item-categories');
        Route::get('brands', [CatalogController::class, 'brands'])->name('api.v1.brands');
        Route::get('item-statuses', [CatalogController::class, 'itemStatuses'])->name('api.v1.item-statuses');
        Route::get('storage-locations', [CatalogController::class, 'storageLocations'])->name('api.v1.storage-locations');

        Route::get('clients/search', [V1ClientController::class, 'search'])->name('api.v1.clients.search');
        Route::get('clients', [V1ClientController::class, 'index'])->name('api.v1.clients.index');
        Route::post('clients', [V1ClientController::class, 'store'])->name('api.v1.clients.store');
        Route::get('clients/{client}', [V1ClientController::class, 'show'])->name('api.v1.clients.show');

        Route::get('pawn-contracts', [V1PawnContractController::class, 'index'])->name('api.v1.pawn-contracts.index');
        Route::post('pawn-contracts', [V1PawnContractController::class, 'store'])->name('api.v1.pawn-contracts.store');
        Route::get('pawn-contracts/{pawnContract}', [V1PawnContractController::class, 'show'])->name('api.v1.pawn-contracts.show');
        Route::post('pawn-contracts/{pawnContract}/redeem', [V1PawnContractController::class, 'redeem'])->name('api.v1.pawn-contracts.redeem');
        Route::post('pawn-contracts/{pawnContract}/pay-interest', [V1PawnContractController::class, 'payInterest'])->name('api.v1.pawn-contracts.pay-interest');

        Route::get('reports/profit', [V1ReportController::class, 'profit'])->name('api.v1.reports.profit');

        Route::post('tools/parse-passport', [AcceptItemController::class, 'parsePassportPhoto'])
            ->name('api.v1.tools.parse-passport');
    });
});

// REST API для клиентов (можно защитить API-токеном). Префикс имён api. — чтобы не конфликтовать с web-маршрутами clients.*
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientApiController::class)->names('api.clients');
});

// Публичное API для списка клиентов (если нужен доступ без авторизации для фронта)
Route::get('clients', [ClientApiController::class, 'index'])->name('api.clients.index.public');
Route::get('clients/{client}', [ClientApiController::class, 'show'])->name('api.clients.show.public');

// agent-teams-portal: звонки MTS и Telegram inbox из lombard.home
Route::middleware('agent.teams.token')->prefix('internal/agent-teams')->group(function () {
    Route::get('mts/health', [AgentTeamsMtsController::class, 'health'])->name('api.agent-teams.mts.health');
    Route::get('mts/calls', [AgentTeamsMtsController::class, 'index'])->name('api.agent-teams.mts.calls');

    Route::get('telegram/health', [\App\Http\Controllers\Api\AgentTeamsTelegramController::class, 'health'])
        ->name('api.agent-teams.telegram.health');
    Route::get('telegram/messages', [\App\Http\Controllers\Api\AgentTeamsTelegramController::class, 'messages'])
        ->name('api.agent-teams.telegram.messages');
    Route::post('telegram/reply', [\App\Http\Controllers\Api\AgentTeamsTelegramController::class, 'reply'])
        ->name('api.agent-teams.telegram.reply');
    Route::get('telegram/lombard-snapshot', [\App\Http\Controllers\Api\AgentTeamsTelegramController::class, 'lombardSnapshot'])
        ->name('api.agent-teams.telegram.lombard-snapshot');
});
