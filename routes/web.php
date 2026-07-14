<?php

use App\Http\Controllers\AcceptItemController;
use App\Http\Controllers\AdminSectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankStatementController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CallCenterController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionContractController;
use App\Http\Controllers\ContactCenterAvitoMatchController;
use App\Http\Controllers\ContactCenterLeadController;
use App\Http\Controllers\ContactCenterVitrineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentLedgerEntryController;
use App\Http\Controllers\DocumentLedgerTemplateController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemStatusController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Management\AcuerdoReportController;
use App\Http\Controllers\Management\LombardReportController;
use App\Http\Controllers\Management\MeetingReportController;
use App\Http\Controllers\Management\TaskController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\PawnContractController;
use App\Http\Controllers\PayrollAccrualController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseContractController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StorageLocationController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Раздача файлов из storage (если симлинк public/storage не работает — как на части хостингов)
Route::get('storage/{path}', function (string $path) {
    $fullPath = storage_path('app/public/'.$path);
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

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

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

    Route::get('section/contact-center', [AdminSectionController::class, 'contactCenter'])
        ->name('section.contact-center')
        ->middleware('role:contact-center,super-admin,manager');

    Route::get('section/clients', [AdminSectionController::class, 'clients'])->name('section.clients');
    Route::get('section/marketing', [AdminSectionController::class, 'marketing'])->name('section.marketing');
    Route::get('section/finance', [AdminSectionController::class, 'finance'])->name('section.finance');
    Route::get('section/management', [AdminSectionController::class, 'management'])->name('section.management');

    Route::resource('management/personnel', EmployeeController::class)
        ->names('management.personnel')
        ->parameters(['personnel' => 'employee']);

    Route::get('management/tasks/board', [TaskController::class, 'board'])->name('management.tasks.board');
    Route::patch('management/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('management.tasks.status');
    Route::resource('management/tasks', TaskController::class)->names('management.tasks');

    Route::prefix('management/reports')->name('management.reports.')->group(function () {
        Route::get('/', [AcuerdoReportController::class, 'index'])->name('index');
        Route::get('/current-asset', [AcuerdoReportController::class, 'currentAsset'])->name('current-asset');
        Route::get('/current-finances', [AcuerdoReportController::class, 'currentFinances'])->name('current-finances');
        Route::prefix('lombard')->name('lombard.')->group(function () {
            Route::get('/pawns', [LombardReportController::class, 'pawnsRedemptions'])->name('pawns');
            Route::get('/gross-profit', [LombardReportController::class, 'grossProfit'])->name('gross-profit');
            Route::get('/pawn-profit', [LombardReportController::class, 'pawnProfit'])->name('pawn-profit');
            Route::get('/sales-profit', [LombardReportController::class, 'salesProfit'])->name('sales-profit');
            Route::get('/inventory', [LombardReportController::class, 'inventorySummary'])->name('inventory');
        });
        Route::get('/meetings', [MeetingReportController::class, 'index'])->name('meetings.index');
        Route::post('/meetings/sync-latest', [MeetingReportController::class, 'syncLatest'])->name('meetings.sync-latest');
        Route::get('/meetings/{meetingReport}', [MeetingReportController::class, 'show'])->name('meetings.show');
    });
    Route::get('section/settings', [AdminSectionController::class, 'settings'])->name('section.settings');
    Route::get('appraiser', function () {
        return view('appraiser.home');
    })->name('appraiser.home');
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
    Route::middleware('role:contact-center,super-admin,manager')->group(function () {
        Route::get('call-center', [CallCenterController::class, 'index'])->name('call-center.index');
        Route::get('call-center/analytics', [CallCenterController::class, 'analytics'])->name('call-center.analytics');
        Route::post('call-center/clear-mts-contacts', [CallCenterController::class, 'clearMtsContacts'])->name('call-center.clear-mts-contacts');
        Route::post('call-center/sync-mts-calls', [CallCenterController::class, 'syncMtsCalls'])->name('call-center.sync-mts-calls');
        Route::post('call-center/sync-mts-recordings', [CallCenterController::class, 'syncMtsRecordings'])->name('call-center.sync-mts-recordings');
        Route::get('call-center/avito/chats', [CallCenterController::class, 'avitoChats'])->name('call-center.avito.chats');
        Route::get('call-center/avito/chats/{chatId}/messages', [CallCenterController::class, 'avitoMessages'])->where('chatId', '.+')->name('call-center.avito.messages');
        Route::post('call-center/avito/chats/{chatId}/messages', [CallCenterController::class, 'sendAvitoMessage'])->where('chatId', '.+')->name('call-center.avito.send');
        Route::get('call-center/telegram/search', [CallCenterController::class, 'telegramSearch'])->name('call-center.telegram.search');
        Route::post('call-center/telegram/open', [CallCenterController::class, 'telegramOpen'])->name('call-center.telegram.open');
        Route::get('call-center/telegram/chats', [CallCenterController::class, 'telegramChats'])->name('call-center.telegram.chats');
        Route::get('call-center/telegram/chats/{chatId}/messages', [CallCenterController::class, 'telegramMessages'])->where('chatId', '.+')->name('call-center.telegram.messages');
        Route::post('call-center/telegram/chats/{chatId}/messages', [CallCenterController::class, 'sendTelegramMessage'])->where('chatId', '.+')->name('call-center.telegram.send');
        Route::get('call-center/create', [CallCenterController::class, 'create'])->name('call-center.create');
        Route::post('call-center', [CallCenterController::class, 'store'])->name('call-center.store');
        Route::get('call-center/{callCenterContact}', [CallCenterController::class, 'show'])->name('call-center.show');
        Route::get('call-center/{callCenterContact}/edit', [CallCenterController::class, 'edit'])->name('call-center.edit');
        Route::put('call-center/{callCenterContact}', [CallCenterController::class, 'update'])->name('call-center.update');
        Route::get('call-center/{callCenterContact}/recording', [CallCenterController::class, 'recording'])->name('call-center.recording');
        Route::post('call-center/{callCenterContact}/transcribe', [CallCenterController::class, 'transcribeRecording'])->name('call-center.transcribe');
        Route::get('call-center/{callCenterContact}/recording-from-mts', [CallCenterController::class, 'recordingFromMts'])->name('call-center.recording-from-mts');
        Route::get('call-center/{callCenterContact}/recording-mts', [CallCenterController::class, 'recordingFromMts'])->name('call-center.recording-mts');

        Route::get('contact-center/leads', [ContactCenterLeadController::class, 'index'])->name('contact-center.leads.index');
        Route::get('contact-center/leads/create', [ContactCenterLeadController::class, 'create'])->name('contact-center.leads.create');
        Route::post('contact-center/leads', [ContactCenterLeadController::class, 'store'])->name('contact-center.leads.store');
        Route::get('contact-center/leads/items/search', [ContactCenterLeadController::class, 'searchItems'])->name('contact-center.leads.items.search');
        Route::get('contact-center/leads/{lead}', [ContactCenterLeadController::class, 'show'])->name('contact-center.leads.show');
        Route::put('contact-center/leads/{lead}', [ContactCenterLeadController::class, 'update'])->name('contact-center.leads.update');
        Route::post('contact-center/leads/{lead}/note', [ContactCenterLeadController::class, 'addNote'])->name('contact-center.leads.note');
        Route::post('contact-center/leads/{lead}/assign', [ContactCenterLeadController::class, 'assignStore'])->name('contact-center.leads.assign');
        Route::post('contact-center/leads/{lead}/reserve', [ContactCenterLeadController::class, 'reserve'])->name('contact-center.leads.reserve');
        Route::post('contact-center/leads/{lead}/cancel-reservation', [ContactCenterLeadController::class, 'cancelReservation'])->name('contact-center.leads.cancel-reservation');
        Route::get('contact-center/contacts/search', [ContactCenterLeadController::class, 'searchContacts'])->name('contact-center.contacts.search');
        Route::get('contact-center/contacts/recent', [ContactCenterLeadController::class, 'recentContacts'])->name('contact-center.contacts.recent');

        Route::get('contact-center/vitrine-priority', [ContactCenterVitrineController::class, 'index'])->name('contact-center.vitrine-priority.index');
        Route::post('contact-center/vitrine-priority/sync-avito', [ContactCenterVitrineController::class, 'syncAvitoInbox'])->name('contact-center.vitrine-priority.sync-avito');
        Route::post('contact-center/vitrine-priority/{item}/discount', [ContactCenterVitrineController::class, 'applyDiscount'])->name('contact-center.vitrine-priority.discount');

        Route::get('contact-center/avito-match', [ContactCenterAvitoMatchController::class, 'index'])->name('contact-center.avito-match.index');
        Route::post('contact-center/avito-match', [ContactCenterAvitoMatchController::class, 'upload'])->name('contact-center.avito-match.upload');
    });

    // Маркетинг: источники трафика, воронка, эффективность, 2ГИС
    Route::get('marketing', [MarketingController::class, 'index'])->name('marketing.index');
    Route::post('marketing/refresh-2gis', [MarketingController::class, 'refresh2Gis'])->name('marketing.refresh-2gis');
    Route::post('marketing/2gis-stats', [MarketingController::class, 'store2GisStat'])->name('marketing.2gis-stats.store');
    Route::post('marketing/2gis-stats/import', [MarketingController::class, 'import2GisStats'])->name('marketing.2gis-stats.import');

    // Договоры залога
    // Кассовые операции
    Route::get('cash', [CashController::class, 'index'])->name('cash.index');
    Route::get('cash/create', [CashController::class, 'create'])->name('cash.create');
    Route::get('cash/report', [CashController::class, 'report'])->name('cash.report');
    Route::post('cash', [CashController::class, 'store'])->name('cash.store');
    Route::get('cash/{cashDocument}', [CashController::class, 'show'])->name('cash.show');
    Route::get('cash/{cashDocument}/edit', [CashController::class, 'edit'])->name('cash.edit');
    Route::put('cash/{cashDocument}', [CashController::class, 'update'])->name('cash.update');
    Route::delete('cash/{cashDocument}', [CashController::class, 'destroy'])->name('cash.destroy');

    // Все документы (сводный список)
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');

    // Шаблоны проводок по типам документов (настройка ОСВ)
    Route::get('document-ledger-templates', [DocumentLedgerTemplateController::class, 'index'])->name('document-ledger-templates.index');
    Route::get('document-ledger-templates/create', [DocumentLedgerTemplateController::class, 'create'])->name('document-ledger-templates.create');
    Route::post('document-ledger-templates', [DocumentLedgerTemplateController::class, 'store'])->name('document-ledger-templates.store');
    Route::delete('document-ledger-templates/{documentLedgerTemplate}', [DocumentLedgerTemplateController::class, 'destroy'])->name('document-ledger-templates.destroy');
    Route::post('document-ledger-entries', [DocumentLedgerEntryController::class, 'store'])->name('document-ledger-entries.store');
    Route::put('document-ledger-entries/{ledgerEntry}', [DocumentLedgerEntryController::class, 'update'])->name('document-ledger-entries.update');

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
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // ФОТ: журнал сотрудников (управление) + документы начисления
    Route::redirect('employees', '/management/personnel')->name('employees.index');
    Route::redirect('employees/create', '/management/personnel/create')->name('employees.create');
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

    Route::middleware('role:super-admin')->group(function () {
        Route::get('settings/system', [SettingController::class, 'index'])->name('settings.system.index');
        Route::post('settings/system', [SettingController::class, 'store'])->name('settings.system.store');
    });
});

// База знаний — публичный просмотр без авторизации (регистрируем после admin, чтобы /knowledge-base/admin/* не перехватывалось как категория)
Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('kb.index');
Route::get('knowledge-base/{categorySlug}', [KnowledgeBaseController::class, 'category'])->name('kb.category');
Route::get('knowledge-base/{categorySlug}/{articleSlug}', [KnowledgeBaseController::class, 'show'])->name('kb.show');
