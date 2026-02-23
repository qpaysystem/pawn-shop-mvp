<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\AcceptItemController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankStatementController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CallCenterController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemStatusController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\PawnContractController;
use App\Http\Controllers\PayrollAccrualController;
use App\Http\Controllers\PurchaseContractController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorageLocationController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Раздача файлов из storage (если симлинк public/storage не работает — как на части хостингов)
Route::get('storage/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);
    if (! is_file($fullPath) || ! str_starts_with(realpath($fullPath), realpath(storage_path('app/public')))) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '(.*)')->name('storage.serve');

// Главная — лендинг ломбарда (гости); авторизованных — в дашборд
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : app(LandingController::class)->lombard();
})->name('home');

// Публичные страницы лендинга (структура как на 5.128.186.3)
Route::get('/lombard', [LandingController::class, 'lombard'])->name('landing.lombard');
Route::get('/buy', fn () => app(LandingController::class)->page('buy'))->name('landing.buy');
Route::get('/contacts', fn () => app(LandingController::class)->page('contacts'))->name('landing.contacts');
Route::get('/about', fn () => app(LandingController::class)->page('about'))->name('landing.about');
Route::get('/catalog', fn () => app(LandingController::class)->page('catalog'))->name('landing.catalog');

// Категории и подразделы
Route::get('/gold', fn () => app(LandingController::class)->page('gold'))->name('landing.gold');
Route::get('/gold/{slug}', fn ($slug) => app(LandingController::class)->section('gold', $slug))->name('landing.gold.section')->where('slug', 'mernie|coins|rings|lom');

Route::get('/fur', fn () => app(LandingController::class)->page('fur'))->name('landing.fur');
Route::get('/fur/{slug}', fn ($slug) => app(LandingController::class)->section('fur', $slug))->name('landing.fur.section')->where('slug', 'sobol|norka');

Route::get('/technical', fn () => app(LandingController::class)->page('technical'))->name('landing.technical');
Route::get('/technical/{slug}', fn ($slug) => app(LandingController::class)->section('technical', $slug))->name('landing.technical.section')->where('slug', 'mv|fr|tv|st');

Route::get('/tool', fn () => app(LandingController::class)->page('tool'))->name('landing.tool');
Route::get('/tool/{slug}', fn ($slug) => app(LandingController::class)->section('tool', $slug))->name('landing.tool.section')->where('slug', 'shurupoverti|perforatori|lobziki');

Route::get('/gadjets', fn () => app(LandingController::class)->page('gadjets'))->name('landing.gadjets');
Route::get('/gadjets/{slug}', fn ($slug) => app(LandingController::class)->section('gadjets', $slug))->name('landing.gadjets.section')->where('slug', 'phone|comp|play|photo');

// Каталог: раздел и товар
Route::get('/catalog/{category_code}', [LandingController::class, 'catalogSection'])->name('landing.catalog.section');
Route::get('/catalog/{category_code}/item/{id}', [LandingController::class, 'catalogItem'])->name('landing.catalog.item');

// Страницы для авторизованных (как на 5.128.186.3): редирект в дашборд или на логин
Route::get('/cabinet', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('landing.cabinet');
Route::get('/zalog', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('landing.zalog');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    // Приём товара (оценщик, менеджер, super-admin) — более специфичные маршруты выше
    Route::get('accept/redemption-search', [AcceptItemController::class, 'redemptionSearch'])->name('accept.redemption-search');
    Route::get('accept', [AcceptItemController::class, 'create'])->name('accept.create');
    Route::post('accept', [AcceptItemController::class, 'store'])->name('accept.store');
    Route::post('accept/parse-passport', [AcceptItemController::class, 'parsePassportPhoto'])->name('accept.parse-passport');
    Route::post('accept/ai-estimate', [AcceptItemController::class, 'estimatePriceWithAi'])->name('accept.ai-estimate');

    // Клиенты
    Route::get('clients/search', [ClientController::class, 'search'])->name('clients.search');
    // GET на sync-lmb (открыли в новой вкладке/закладка) — редирект на карточку клиента
    Route::get('clients/{client}/sync-lmb', function (App\Models\Client $client) {
        return redirect()->route('clients.show', $client)->with('info', 'Используйте кнопку «Загрузить из 1С» на странице клиента.');
    });
    Route::post('clients/{client}/sync-lmb', [ClientController::class, 'syncLmb'])->name('clients.sync-lmb');
    Route::resource('clients', ClientController::class);

    // Товары
    Route::resource('items', ItemController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);

    // Колл-центр
    Route::get('call-center', [CallCenterController::class, 'index'])->name('call-center.index');
    Route::get('call-center/analytics', [CallCenterController::class, 'analytics'])->name('call-center.analytics');
    Route::get('call-center/create', [CallCenterController::class, 'create'])->name('call-center.create');
    Route::post('call-center', [CallCenterController::class, 'store'])->name('call-center.store');
    Route::get('call-center/{callCenterContact}', [CallCenterController::class, 'show'])->name('call-center.show');
    Route::get('call-center/{callCenterContact}/edit', [CallCenterController::class, 'edit'])->name('call-center.edit');
    Route::put('call-center/{callCenterContact}', [CallCenterController::class, 'update'])->name('call-center.update');
    Route::post('call-center/clear-mts-contacts', [CallCenterController::class, 'clearMtsContacts'])->name('call-center.clear-mts-contacts');
    Route::post('call-center/sync-mts-calls', [CallCenterController::class, 'syncMtsCalls'])->name('call-center.sync-mts-calls');
    Route::post('call-center/sync-mts-recordings', [CallCenterController::class, 'syncMtsRecordings'])->name('call-center.sync-mts-recordings');
    Route::get('call-center/{callCenterContact}/recording', [CallCenterController::class, 'recording'])->name('call-center.recording');
    Route::get('call-center/{callCenterContact}/recording-mts', [CallCenterController::class, 'recordingFromMts'])->name('call-center.recording-mts');
    Route::post('call-center/{callCenterContact}/transcribe', [CallCenterController::class, 'transcribeRecording'])->name('call-center.transcribe');

    // Договоры залога
    // Кассовые операции
    Route::get('cash', [CashController::class, 'index'])->name('cash.index');
    Route::get('cash/create', [CashController::class, 'create'])->name('cash.create');
    Route::get('cash/report', [CashController::class, 'report'])->name('cash.report');
    Route::post('cash', [CashController::class, 'store'])->name('cash.store');
    Route::delete('cash/{cashDocument}', [CashController::class, 'destroy'])->name('cash.destroy');

    // План счетов и отчётность
    Route::get('chart-of-accounts', [ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
    Route::get('chart-of-accounts/turnover-balance', [ChartOfAccountsController::class, 'turnoverBalance'])->name('chart-of-accounts.turnover-balance');
    Route::get('chart-of-accounts/{account}', [ChartOfAccountsController::class, 'show'])->name('chart-of-accounts.show');

    // Расходы: виды расходов + документы начисления
    Route::resource('expense-types', ExpenseTypeController::class)->except(['show']);
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');

    // ФОТ: сотрудники + документы начисления
    Route::resource('employees', EmployeeController::class);
    Route::get('payroll-accruals', [PayrollAccrualController::class, 'index'])->name('payroll-accruals.index');
    Route::get('payroll-accruals/create', [PayrollAccrualController::class, 'create'])->name('payroll-accruals.create');
    Route::post('payroll-accruals', [PayrollAccrualController::class, 'store'])->name('payroll-accruals.store');
    Route::get('payroll-accruals/{payrollAccrual}', [PayrollAccrualController::class, 'show'])->name('payroll-accruals.show');

    // Банк: расчётные счета + выписки
    Route::resource('bank-accounts', BankAccountController::class)->except(['show']);
    Route::get('bank-accounts/{bankAccount}/statements', [BankStatementController::class, 'index'])->name('bank-accounts.statements.index');
    Route::get('bank-accounts/{bankAccount}/statements/create', [BankStatementController::class, 'create'])->name('bank-accounts.statements.create');
    Route::post('bank-accounts/{bankAccount}/statements', [BankStatementController::class, 'store'])->name('bank-accounts.statements.store');
    Route::get('bank-accounts/{bankAccount}/statements/{statement}', [BankStatementController::class, 'show'])->name('bank-accounts.statements.show');
    Route::post('bank-accounts/{bankAccount}/statements/{statement}/lines', [BankStatementController::class, 'addLine'])->name('bank-accounts.statements.lines.store');
    Route::get('bank-accounts/{bankAccount}/statements/{statement}/download', [BankStatementController::class, 'downloadFile'])->name('bank-accounts.statements.download');

    Route::get('pawn-contracts', [PawnContractController::class, 'index'])->name('pawn-contracts.index');
    Route::get('pawn-contracts/{pawnContract}', [PawnContractController::class, 'show'])->name('pawn-contracts.show');
    Route::get('pawn-contracts/{pawnContract}/print', [PawnContractController::class, 'print'])->name('pawn-contracts.print');
    Route::post('pawn-contracts/{pawnContract}/redeem', [PawnContractController::class, 'redeem'])->name('pawn-contracts.redeem');

    // Договоры комиссии
    Route::get('commission-contracts', [CommissionContractController::class, 'index'])->name('commission-contracts.index');
    // Договоры скупки
    Route::get('purchase-contracts', [PurchaseContractController::class, 'index'])->name('purchase-contracts.index');
    Route::get('purchase-contracts/{purchaseContract}', [PurchaseContractController::class, 'show'])->name('purchase-contracts.show');
    Route::get('purchase-contracts/{purchaseContract}/print', [PurchaseContractController::class, 'print'])->name('purchase-contracts.print');
    Route::get('commission-contracts/{commissionContract}', [CommissionContractController::class, 'show'])->name('commission-contracts.show');
    Route::get('commission-contracts/{commissionContract}/print', [CommissionContractController::class, 'print'])->name('commission-contracts.print');
    Route::post('commission-contracts/{commissionContract}/sold', [CommissionContractController::class, 'markSold'])->name('commission-contracts.sold');

    // Справочники
    Route::resource('item-categories', ItemCategoryController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('item-statuses', ItemStatusController::class);
    Route::resource('storage-locations', StorageLocationController::class);

    // База знаний: сначала маршруты админки (чтобы /knowledge-base/admin/* не перехватывались как категория/статья)
    Route::prefix('knowledge-base/admin')->name('kb.')->group(function () {
        Route::get('categories', [KnowledgeBaseController::class, 'categoriesIndex'])->name('categories.index');
        Route::get('categories/create', [KnowledgeBaseController::class, 'categoryCreate'])->name('categories.create');
        Route::post('categories', [KnowledgeBaseController::class, 'categoryStore'])->name('categories.store');
        Route::get('categories/{kbCategory}/edit', [KnowledgeBaseController::class, 'categoryEdit'])->name('categories.edit');
        Route::put('categories/{kbCategory}', [KnowledgeBaseController::class, 'categoryUpdate'])->name('categories.update');
        Route::delete('categories/{kbCategory}', [KnowledgeBaseController::class, 'categoryDestroy'])->name('categories.destroy');
        Route::get('articles', [KnowledgeBaseController::class, 'articlesIndex'])->name('articles.index');
        Route::get('articles/create', [KnowledgeBaseController::class, 'articleCreate'])->name('articles.create');
        Route::post('articles', [KnowledgeBaseController::class, 'articleStore'])->name('articles.store');
        Route::get('articles/{kbArticle}/edit', [KnowledgeBaseController::class, 'articleEdit'])->name('articles.edit');
        Route::put('articles/{kbArticle}', [KnowledgeBaseController::class, 'articleUpdate'])->name('articles.update');
        Route::post('articles/{kbArticle}/photo', [KnowledgeBaseController::class, 'articlePhotoStore'])->name('articles.photo.store');
        Route::delete('articles/{kbArticle}', [KnowledgeBaseController::class, 'articleDestroy'])->name('articles.destroy');
    });

    // Магазины и пользователи — только super-admin
    Route::resource('stores', StoreController::class)->middleware('role:super-admin');
    Route::resource('users', UserController::class)->except(['show'])->middleware('role:super-admin');
});

// База знаний — публичный просмотр без авторизации (регистрируем после admin, чтобы /knowledge-base/admin/* не перехватывалось как категория)
Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('kb.index');
Route::get('knowledge-base/{categorySlug}', [KnowledgeBaseController::class, 'category'])->name('kb.category');
Route::get('knowledge-base/{categorySlug}/{articleSlug}', [KnowledgeBaseController::class, 'show'])->name('kb.show');
