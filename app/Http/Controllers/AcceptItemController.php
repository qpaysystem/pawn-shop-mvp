<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\Client;
use App\Models\ClientVisit;
use App\Models\CommissionContract;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\StorageLocation;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Единая форма приёма товара: выбор типа (залог/комиссия),
 * клиент (поиск или новый), данные товара, фото, сумма и сроки.
 */
class AcceptItemController extends Controller
{
    /** Поиск клиентов и договоров залога для оформления выкупа (ФИО, телефон, номер договора). */
    public function redemptionSearch(Request $request)
    {
        if (! Auth::user()->canCreateContracts()) {
            abort(403);
        }
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['clients' => []]);
        }
        $allowedStoreIds = Auth::user()->allowedStoreIds();
        if ($allowedStoreIds === []) {
            return response()->json(['clients' => []]);
        }
        try {
            $byContractNumber = PawnContract::query()
                ->where('is_redeemed', false)
                ->whereIn('store_id', $allowedStoreIds)
                ->where('contract_number', 'like', '%' . $q . '%')
                ->pluck('client_id')
                ->unique()
                ->values()
                ->all();
            $clientIdsByNameOrPhone = Client::query()
                ->where(function ($query) use ($q) {
                    $query->where('full_name', 'like', '%' . $q . '%')
                        ->orWhere('last_name', 'like', '%' . $q . '%')
                        ->orWhere('first_name', 'like', '%' . $q . '%')
                        ->orWhere('phone', 'like', '%' . $q . '%');
                })
                ->whereHas('pawnContracts', function ($pq) use ($allowedStoreIds) {
                    $pq->where('is_redeemed', false)->whereIn('store_id', $allowedStoreIds);
                })
                ->pluck('id')
                ->all();
            $clientIds = array_values(array_unique(array_merge($byContractNumber, $clientIdsByNameOrPhone)));
            if ($clientIds === []) {
                return response()->json(['clients' => []]);
            }
            $clients = Client::query()
                ->whereIn('id', $clientIds)
                ->get(['id', 'full_name', 'last_name', 'first_name', 'patronymic', 'phone']);
            $contracts = PawnContract::query()
                ->with(['item', 'store'])
                ->whereIn('client_id', $clientIds)
                ->where('is_redeemed', false)
                ->whereIn('store_id', $allowedStoreIds)
                ->orderByDesc('loan_date')
                ->get();
            $byClient = [];
            foreach ($contracts as $c) {
                $loanAmount = $c->loan_amount !== null ? (float) $c->loan_amount : 0;
                $buybackAmount = $c->buyback_amount !== null ? (float) $c->buyback_amount : 0;
                $byClient[$c->client_id][] = [
                    'id' => $c->id,
                    'contract_number' => $c->contract_number,
                    'item_name' => $c->item ? $c->item->name : '—',
                    'loan_amount' => $loanAmount,
                    'loan_percent' => $c->loan_percent !== null ? (float) $c->loan_percent : 0,
                    'buyback_amount' => $buybackAmount,
                    'redemption_amount' => $buybackAmount > 0 ? $buybackAmount : $c->redemption_amount,
                    'expiry_date' => $c->expiry_date !== null && $c->expiry_date !== ''
                ? \Carbon\Carbon::parse($c->expiry_date)->format('d.m.Y')
                : null,
                    'store_name' => $c->store ? $c->store->name : null,
                ];
            }
            $result = [];
            foreach ($clients as $client) {
                $result[] = [
                    'id' => $client->id,
                    'full_name' => $client->full_name ?: trim(implode(' ', array_filter([$client->last_name, $client->first_name, $client->patronymic]))),
                    'phone' => $client->phone,
                    'contracts' => $byClient[$client->id] ?? [],
                ];
            }
            return response()->json(['clients' => $result]);
        } catch (\Throwable $e) {
            Log::error('AcceptItemController::redemptionSearch', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['clients' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function create()
    {
        if (! Auth::user()->canCreateContracts()) {
            abort(403, 'Нет прав на приём товара.');
        }
        $stores = \App\Models\Store::whereIn('id', Auth::user()->allowedStoreIds())->where('is_active', true)->orderBy('name')->get();
        $categories = \App\Models\ItemCategory::orderBy('name')->get();
        $brands = \App\Models\Brand::orderBy('name')->get();
        $statuses = ItemStatus::orderBy('name')->get();
        $storageLocations = StorageLocation::whereIn('store_id', Auth::user()->allowedStoreIds())->orderBy('name')->get();

        return view('accept.create', compact('stores', 'categories', 'brands', 'statuses', 'storageLocations'));
    }

    public function store(Request $request)
    {
        if (! Auth::user()->canCreateContracts()) {
            abort(403);
        }

        $request->validate([
            'visit_purpose' => 'nullable|in:appraisal,redemption,non_target,identification',
            'contract_type' => 'required|in:pawn,commission,purchase',
            'store_id' => 'required|exists:stores,id',
            'client_id' => 'nullable|exists:clients,id',
            'client_last_name' => 'nullable|string|max:100',
            'client_first_name' => 'nullable|string|max:100',
            'client_patronymic' => 'nullable|string|max:100',
            'client_birth_date' => 'nullable|string|max:20',
            'client_passport_series_number' => 'nullable|string|max:20',
            'client_passport_issued_by' => 'nullable|string|max:500',
            'client_passport_issued_at' => 'nullable|string|max:20',
            'client_phone' => [Rule::requiredIf(! $request->filled('client_id')), 'nullable', 'string', 'max:50'],
            'client_email' => 'nullable|email|max:255',
            'client_passport' => 'nullable|string|max:2000',
            'item_name' => 'required|string|max:255',
            'item_description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|exists:item_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'storage_location_id' => 'nullable|exists:storage_locations,id',
            'status_id' => 'required|exists:item_statuses,id',
            'initial_price' => 'nullable|numeric|min:0',
            'current_price' => 'nullable|numeric|min:0',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:5120',
            // Залог
            'loan_amount' => 'required_if:contract_type,pawn|nullable|numeric|min:0',
            'loan_percent' => 'nullable|numeric|min:0',
            'loan_date' => 'required_if:contract_type,pawn|nullable|date',
            'expiry_date_pawn' => 'required_if:contract_type,pawn|nullable|date',
            // Комиссия
            'commission_percent' => 'nullable|numeric|min:0',
            'seller_price' => 'nullable|numeric|min:0',
            'expiry_date_commission' => 'nullable|date',
            // Скупка
            'purchase_amount' => 'required_if:contract_type,purchase|nullable|numeric|min:0',
            'purchase_date' => 'required_if:contract_type,purchase|nullable|date',
        ]);

        $storeId = (int) $request->store_id;
        if (! in_array($storeId, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $visitPurpose = in_array($request->input('visit_purpose'), ['appraisal', 'redemption', 'non_target', 'identification'], true)
            ? $request->input('visit_purpose')
            : 'appraisal';

        $lastName = trim((string) $request->input('client_last_name', ''));
        $firstName = trim((string) $request->input('client_first_name', ''));
        $patronymic = trim((string) $request->input('client_patronymic', ''));
        $fullName = $this->buildFullName($lastName, $firstName, $patronymic);
        $passportData = $this->normalizeClientPassportData($request);
        if (! $request->filled('client_id') && $fullName === '') {
            return redirect()->back()->withErrors(['client_last_name' => 'Укажите фамилию и имя клиента или выберите клиента из поиска.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $client = $request->client_id
                ? Client::findOrFail($request->client_id)
                : $this->findOrCreateClient($request, $lastName, $firstName, $patronymic, $passportData);

            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $path = $file->store('items', 'public');
                    $photos[] = $path;
                }
            }

            $categoryId = $request->category_id;
            $brandId = $request->brand_id;
            if (is_array($categoryId)) {
                $categoryId = $categoryId[0] ?? null;
            }
            if (is_array($brandId)) {
                $brandId = $brandId[0] ?? null;
            }

            $item = Item::create([
                'name' => $request->item_name,
                'description' => $request->item_description ?: null,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'brand_id' => $brandId ? (int) $brandId : null,
                'store_id' => $storeId,
                'storage_location_id' => $request->storage_location_id ? (int) $request->storage_location_id : null,
                'status_id' => (int) $request->status_id,
                'barcode' => Item::generateBarcode(),
                'photos' => $photos ? json_encode($photos) : null,
                'initial_price' => $request->initial_price ? (float) $request->initial_price : null,
                'current_price' => $request->current_price ? (float) $request->current_price : (float) ($request->initial_price ?: 0),
            ]);

            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => null,
                'new_status_id' => $item->status_id,
                'changed_by' => Auth::id(),
            ]);

            if ($request->contract_type === 'pawn') {
                $loanAmount = (float) $request->loan_amount;
                $percent = (float) ($request->loan_percent ?: 0);
                $buyback = $loanAmount + ($loanAmount * $percent / 100);

                $contract = PawnContract::create([
                    'contract_number' => PawnContract::generateContractNumber(),
                    'client_id' => $client->id,
                    'item_id' => $item->id,
                    'appraiser_id' => Auth::id(),
                    'store_id' => $storeId,
                    'loan_amount' => $loanAmount,
                    'loan_percent' => $percent,
                    'loan_date' => $request->loan_date,
                    'expiry_date' => $request->expiry_date_pawn,
                    'buyback_amount' => $buyback,
                ]);

                $loanOpType = CashOperationType::findByName('Выдача займа');
                if ($loanOpType) {
                    $docNum = CashDocument::generateDocumentNumber($storeId, 'expense');
                    CashDocument::create([
                        'store_id' => $storeId,
                        'client_id' => $client->id,
                        'operation_type_id' => $loanOpType->id,
                        'document_number' => $docNum,
                        'document_date' => $request->loan_date,
                        'amount' => $loanAmount,
                        'comment' => 'Договор залога №' . $contract->contract_number,
                        'created_by' => Auth::id(),
                    ]);
                }

                DB::commit();

                $this->createClientVisitForPawn($storeId, $client->id, $visitPurpose, $contract->id);
                $ledger = app(LedgerService::class);
                $ledger->post(\App\Models\Account::CODE_LOANS, \App\Models\Account::CODE_CASH, $loanAmount, \Carbon\Carbon::parse($request->loan_date), $storeId, 'pawn_contract', $contract->id, 'Договор залога №' . $contract->contract_number, $client->id);
                $ledger->post(\App\Models\Account::CODE_PLEDGE, \App\Models\Account::CODE_SETTLEMENTS_OTHER, $loanAmount, \Carbon\Carbon::parse($request->loan_date), $storeId, 'pawn_contract', $contract->id, 'Поступление товара в залог №' . $contract->contract_number, $client->id);

                return redirect()->route('pawn-contracts.print', $contract)->with('success', 'Договор залога создан. Товар принят.');
            }

            if ($request->contract_type === 'purchase') {
                $purchaseAmount = (float) $request->purchase_amount;
                $purchaseDate = $request->purchase_date;

                $contract = PurchaseContract::create([
                    'contract_number' => PurchaseContract::generateContractNumber(),
                    'client_id' => $client->id,
                    'item_id' => $item->id,
                    'appraiser_id' => Auth::id(),
                    'store_id' => $storeId,
                    'purchase_amount' => $purchaseAmount,
                    'purchase_date' => $purchaseDate,
                ]);

                $paymentOpType = CashOperationType::findByName('Оплата продавцу');
                $cashDoc = null;
                if ($paymentOpType) {
                    $docNum = CashDocument::generateDocumentNumber($storeId, 'expense');
                    $cashDoc = CashDocument::create([
                        'store_id' => $storeId,
                        'client_id' => $client->id,
                        'operation_type_id' => $paymentOpType->id,
                        'document_number' => $docNum,
                        'document_date' => $purchaseDate,
                        'amount' => $purchaseAmount,
                        'comment' => 'Договор скупки №' . $contract->contract_number,
                        'created_by' => Auth::id(),
                    ]);
                }

                DB::commit();

                $this->createClientVisitForPurchase($storeId, $client->id, $visitPurpose, $contract->id);
                $ledger = app(LedgerService::class);
                $ledger->post(Account::CODE_GOODS, Account::CODE_CASH, $purchaseAmount, \Carbon\Carbon::parse($purchaseDate), $storeId, 'purchase_contract', $contract->id, 'Договор скупки №' . $contract->contract_number, $contract->client_id);
                // Проводки по кассовому документу «Оплата продавцу»: Дт 60 Кт 50
                if ($cashDoc) {
                    $ledger->post(Account::CODE_SUPPLIERS, Account::CODE_CASH, $purchaseAmount, \Carbon\Carbon::parse($purchaseDate), $storeId, 'cash_document', $cashDoc->id, $cashDoc->document_number . ' ' . ($cashDoc->comment ?? ''), $client->id);
                }

                return redirect()->route('purchase-contracts.print', $contract)->with('success', 'Договор скупки создан. Товар выкуплен.');
            }

            $sellerPrice = (float) ($request->seller_price ?: 0);
            $commissionPercent = (float) ($request->commission_percent ?: 0);
            $commissionAmount = $sellerPrice * $commissionPercent / 100;
            $clientPrice = $sellerPrice - $commissionAmount;

            $contract = CommissionContract::create([
                'contract_number' => CommissionContract::generateContractNumber(),
                'client_id' => $client->id,
                'item_id' => $item->id,
                'appraiser_id' => Auth::id(),
                'store_id' => $storeId,
                'commission_percent' => $commissionPercent,
                'commission_amount' => $commissionAmount,
                'seller_price' => $sellerPrice,
                'client_price' => $clientPrice,
                'expiry_date' => $request->expiry_date_commission,
            ]);

            DB::commit();

            $this->createClientVisitForCommission($storeId, $client->id, $visitPurpose, $contract->id);

            return redirect()->route('commission-contracts.print', $contract)->with('success', 'Договор комиссии создан. Товар принят.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** Создать запись визита после успешного оформления договора залога (вне транзакции). */
    private function createClientVisitForPawn(int $storeId, int $clientId, string $visitPurpose, int $contractId): void
    {
        try {
            ClientVisit::create([
                'store_id' => $storeId,
                'client_id' => $clientId,
                'visit_purpose' => $visitPurpose,
                'visited_at' => now(),
                'created_by' => Auth::id(),
                'pawn_contract_id' => $contractId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClientVisit не создан для договора залога', [
                'store_id' => $storeId,
                'client_id' => $clientId,
                'pawn_contract_id' => $contractId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /** Создать запись визита после успешного оформления договора скупки. */
    private function createClientVisitForPurchase(int $storeId, int $clientId, string $visitPurpose, int $contractId): void
    {
        try {
            ClientVisit::create([
                'store_id' => $storeId,
                'client_id' => $clientId,
                'visit_purpose' => $visitPurpose,
                'visited_at' => now(),
                'created_by' => Auth::id(),
                'purchase_contract_id' => $contractId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClientVisit не создан для договора скупки', [
                'store_id' => $storeId,
                'client_id' => $clientId,
                'purchase_contract_id' => $contractId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Создать запись визита после успешного оформления договора комиссии. */
    private function createClientVisitForCommission(int $storeId, int $clientId, string $visitPurpose, int $contractId): void
    {
        try {
            ClientVisit::create([
                'store_id' => $storeId,
                'client_id' => $clientId,
                'visit_purpose' => $visitPurpose,
                'visited_at' => now(),
                'created_by' => Auth::id(),
                'commission_contract_id' => $contractId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClientVisit не создан для договора комиссии', [
                'store_id' => $storeId,
                'client_id' => $clientId,
                'commission_contract_id' => $contractId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Распознать текст с фото паспорта.
     * Приоритет: Deep Seek (vision + извлечение в одном запросе) → Gemini → Vision API → regex/OpenAI.
     */
    public function parsePassportPhoto(Request $request)
    {
        if (! Auth::user()->canCreateContracts()) {
            abort(403);
        }
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $deepseekKey = config('services.deepseek.api_key');
        $geminiKey = config('services.gemini.api_key');
        $visionKey = config('services.google_vision.api_key');

        if (($deepseekKey === '' || $deepseekKey === null) && ($geminiKey === '' || $geminiKey === null) && ($visionKey === '' || $visionKey === null)) {
            return response()->json([
                'error' => 'Укажите DEEPSEEK_API_KEY, GEMINI_API_KEY или GOOGLE_VISION_API_KEY в .env. На боевом после изменения .env выполните: php artisan config:clear',
                'passport_data' => '',
            ], 422);
        }

        $file = $request->file('photo');
        $fullPath = $file->getRealPath();
        if (! is_file($fullPath)) {
            return response()->json([
                'error' => 'Файл недоступен после загрузки.',
                'passport_data' => '',
            ], 422);
        }

        try {
            // 1. Deep Seek Vision — анализ фото и извлечение ФИО в одном запросе
            if ($deepseekKey !== '' && $deepseekKey !== null) {
                $deepseekResult = $this->runDeepSeekVisionExtract($fullPath, $deepseekKey);
                if ($deepseekResult !== null) {
                    return response()->json([
                        'passport_data' => $deepseekResult['raw_text'] ?? '',
                        'success' => true,
                        'fields' => $deepseekResult['fields'],
                        'parsed_by' => 'deepseek',
                        'llm_error' => null,
                    ]);
                }
                // Deep Seek не сработал — идём в fallback
            }

            // 2. Fallback: Gemini / Vision OCR → OpenAI / regex
            $text = $geminiKey !== '' && $geminiKey !== null
                ? $this->runGeminiOcr($fullPath, $geminiKey)
                : $this->runGoogleVisionOcr($fullPath, $visionKey);
            $text = $this->cleanOcrPassportText($text ?? '');
            $llmResult = $this->parsePassportWithLlm($text);
            $llmError = null;
            if ($llmResult !== null && ($llmResult['ok'] ?? false) && ! empty($llmResult['fields'])) {
                $fields = $llmResult['fields'];
                $parsedBy = $llmResult['provider'] ?? 'openai';
            } else {
                $fields = $this->parsePassportFields($text);
                $parsedBy = 'regex';
                if ($llmResult !== null && isset($llmResult['reason'])) {
                    $llmError = $llmResult['message'] ?? $llmResult['reason'];
                }
            }
            return response()->json([
                'passport_data' => $text,
                'success' => true,
                'fields' => $fields,
                'parsed_by' => $parsedBy,
                'llm_error' => $llmError,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Passport OCR error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $msg = $e->getMessage();
            if (str_contains($msg, 'Connection') || str_contains($msg, 'cURL') || str_contains($msg, 'SSL') || str_contains($msg, 'timed out')) {
                $msg = 'Нет доступа к сервису распознавания с сервера (сеть или SSL). Проверьте исходящие запросы и при необходимости выполните на сервере: php artisan config:clear';
            }
            return response()->json([
                'error' => $msg,
                'passport_data' => '',
            ], 422);
        }
    }

    /**
     * AI-оценка товара через Deep Seek. По данным категории, названия, описания и бренда предлагает цену.
     */
    public function estimatePriceWithAi(Request $request)
    {
        if (! Auth::user()->canCreateContracts()) {
            abort(403);
        }
        $request->validate([
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|exists:item_categories,id',
            'item_description' => 'nullable|string|max:2000',
            'brand_name' => 'nullable|string|max:255',
        ]);

        $deepseekKey = config('services.deepseek.api_key');
        if ($deepseekKey === '' || $deepseekKey === null) {
            return response()->json(['error' => 'Укажите DEEPSEEK_API_KEY в .env'], 422);
        }

        $category = ItemCategory::find($request->category_id);
        $categoryName = $category ? $category->name : 'Товар';
        $itemName = trim($request->item_name);
        $description = trim($request->item_description ?? '');
        $brandName = trim($request->brand_name ?? '');

        $config = $category->evaluation_config ?? [];
        $customPrompt = $config['ai_prompt_suffix'] ?? '';

        $context = "Категория: {$categoryName}\nНазвание: {$itemName}\n";
        if ($description !== '') {
            $context .= "Описание: {$description}\n";
        }
        if ($brandName !== '') {
            $context .= "Бренд: {$brandName}\n";
        }
        if ($customPrompt !== '') {
            $context .= "\nДополнительные указания для оценки: {$customPrompt}\n";
        }

        $prompt = <<<PROMPT
Ты эксперт-оценщик ломбарда. Оцени залоговую стоимость (сумма займа) и примерную цену перепродажи товара на основе данных ниже.
Контекст товара:
{$context}

Верни ТОЛЬКО валидный JSON без markdown и комментариев, с ключами:
- loan_amount: число (рекомендуемая сумма займа в рублях, целое)
- sale_price: число (оценка рыночной цены продажи в рублях, целое)
- explanation: краткое пояснение оценки (1-2 предложения)

Учитывай типичные цены на российском рынке. Будь консервативен в оценке залога (обычно 50-70% от рыночной цены).
PROMPT;

        try {
            $model = config('services.deepseek.model', 'deepseek-chat');
            $response = Http::timeout(30)
                ->withToken($deepseekKey)
                ->post('https://api.deepseek.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ты эксперт-оценщик. Отвечай только валидным JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                    'max_tokens' => 512,
                ]);

            if (! $response->successful()) {
                Log::warning('Deep Seek API ошибка (оценка)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return response()->json([
                    'error' => 'Ошибка API Deep Seek: ' . ($response->json('error.message') ?? $response->body()),
                ], 502);
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? null;
            if ($content === null || $content === '') {
                return response()->json(['error' => 'Пустой ответ от ИИ'], 502);
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return response()->json(['error' => 'ИИ вернул некорректный ответ'], 502);
            }

            $loanAmount = (int) ($decoded['loan_amount'] ?? 0);
            $salePrice = (int) ($decoded['sale_price'] ?? $loanAmount);
            $explanation = trim((string) ($decoded['explanation'] ?? ''));

            $avitoListings = [];
            $similarImages = [];
            $serperKey = config('services.serper.api_key');
            if ($serperKey !== '' && $serperKey !== null) {
                $searchQuery = trim("{$itemName} {$brandName}");
                $avitoListings = $this->fetchAvitoListings($searchQuery, $serperKey);
                $similarImages = $this->fetchSimilarImages($searchQuery, $serperKey);
            }

            return response()->json([
                'success' => true,
                'loan_amount' => max(0, $loanAmount),
                'sale_price' => max(0, $salePrice),
                'initial_price' => max(0, $salePrice),
                'current_price' => max(0, $salePrice),
                'explanation' => $explanation,
                'avito_listings' => $avitoListings,
                'similar_images' => $similarImages,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Deep Seek оценка: исключение', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    /** Поиск похожих объявлений на Авито через Serper. */
    private function fetchAvitoListings(string $query, string $apiKey): array
    {
        try {
            $searchQ = 'site:avito.ru ' . $query;
            $response = Http::timeout(15)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post('https://google.serper.dev/search', [
                    'q' => $searchQ,
                    'num' => 8,
                    'gl' => 'ru',
                    'hl' => 'ru',
                ]);

            if (! $response->successful()) {
                return [];
            }
            $organic = $response->json('organic') ?? [];
            return array_slice(array_map(function ($item) {
                return [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                ];
            }, $organic), 0, 6);
        } catch (\Throwable $e) {
            Log::debug('Serper Avito search failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /** Поиск похожих изображений через Serper. */
    private function fetchSimilarImages(string $query, string $apiKey): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post('https://google.serper.dev/images', [
                    'q' => $query,
                    'num' => 8,
                    'gl' => 'ru',
                    'hl' => 'ru',
                ]);

            if (! $response->successful()) {
                return [];
            }
            $images = $response->json('images') ?? [];
            return array_slice(array_map(function ($item) {
                return [
                    'title' => $item['title'] ?? '',
                    'imageUrl' => $item['imageUrl'] ?? $item['thumbnailUrl'] ?? '',
                    'thumbnailUrl' => $item['thumbnailUrl'] ?? $item['imageUrl'] ?? '',
                    'link' => $item['link'] ?? '',
                ];
            }, $images), 0, 6);
        } catch (\Throwable $e) {
            Log::debug('Serper images search failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Анализ фото паспорта через Deep Seek Vision и извлечение ФИО + прочих полей в одном запросе.
     * API совместим с OpenAI (https://api.deepseek.com).
     */
    private function runDeepSeekVisionExtract(string $imagePath, string $apiKey): ?array
    {
        $content = file_get_contents($imagePath);
        if ($content === false || strlen($content) === 0) {
            return null;
        }
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
        if (strlen($content) > 4 * 1024 * 1024) {
            $resized = $this->resizeImageForVision($imagePath, $content);
            if ($resized === null) {
                return null;
            }
            $content = $resized;
            $mime = 'image/jpeg';
        }
        $base64 = base64_encode($content);
        $dataUrl = 'data:' . $mime . ';base64,' . $base64;

        $model = config('services.deepseek.model', 'deepseek-chat');
        $prompt = <<<'PROMPT'
На изображении разворот паспорта РФ. Извлеки данные и верни ТОЛЬКО валидный JSON без markdown и комментариев, ровно с ключами:
- last_name: фамилия (ЗАГЛАВНЫМИ буквами)
- first_name: имя (ЗАГЛАВНЫМИ буквами)
- patronymic: отчество (ЗАГЛАВНЫМИ буквами)
- birth_date: дата рождения в формате ДД.ММ.ГГГГ или пустая строка
- passport_series_number: серия и номер паспорта (например "12 34 567890" или "1234567890")
- issued_by: кем выдан паспорт
- issued_at: дата выдачи в формате ДД.ММ.ГГГГ или пустая строка

Если поле не видно — верни для него пустую строку "". Не придумывай данные.
PROMPT;

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 1024,
            ]);

        if (! $response->successful()) {
            Log::warning('Deep Seek API ошибка', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? null;
        if ($content === null || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'raw_text' => $content,
            'fields' => $this->normalizeParsedFields([
                'last_name' => $decoded['last_name'] ?? '',
                'first_name' => $decoded['first_name'] ?? '',
                'patronymic' => $decoded['patronymic'] ?? '',
                'birth_date' => $decoded['birth_date'] ?? '',
                'passport_series_number' => $decoded['passport_series_number'] ?? '',
                'issued_by' => $decoded['issued_by'] ?? '',
                'issued_at' => $decoded['issued_at'] ?? '',
            ]),
        ];
    }

    /** Нормализация полей парсинга (trim, fio для совместимости). */
    private function normalizeParsedFields(array $fields): array
    {
        $lastName = trim((string) ($fields['last_name'] ?? ''));
        $firstName = trim((string) ($fields['first_name'] ?? ''));
        $patronymic = trim((string) ($fields['patronymic'] ?? ''));
        $fio = trim(implode(' ', array_filter([$lastName, $firstName, $patronymic])));

        return [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'patronymic' => $patronymic,
            'fio' => $fio,
            'birth_date' => trim((string) ($fields['birth_date'] ?? '')),
            'passport_series_number' => trim((string) ($fields['passport_series_number'] ?? '')),
            'issued_by' => trim((string) ($fields['issued_by'] ?? '')),
            'issued_at' => trim((string) ($fields['issued_at'] ?? '')),
        ];
    }

    /** Распознавание через Google Cloud Vision API. */
    private function runGoogleVisionOcr(string $imagePath, string $apiKey): ?string
    {
        $content = file_get_contents($imagePath);
        if ($content === false || strlen($content) === 0) {
            return null;
        }
        $base64 = base64_encode($content);
        if (strlen($base64) > 4 * 1024 * 1024) {
            $content = $this->resizeImageForVision($imagePath, $content);
            if ($content === null) {
                return null;
            }
            $base64 = base64_encode($content);
        }
        $response = Http::timeout(20)->withHeaders(['Content-Type' => 'application/json'])->post(
            'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey),
            [
                'requests' => [
                    [
                        'image' => ['content' => $base64],
                        'features' => [['type' => 'TEXT_DETECTION']],
                    ],
                ],
            ]
        );
        if (! $response->successful()) {
            $err = $response->json('error');
            $msg = $err['message'] ?? $response->body();
            throw new \RuntimeException($msg);
        }
        $data = $response->json('responses.0');
        if (empty($data)) {
            return null;
        }
        if (! empty($data['error'])) {
            throw new \RuntimeException($data['error']['message'] ?? 'Vision API error');
        }
        $text = $data['fullTextAnnotation']['text'] ?? ($data['textAnnotations'][0]['description'] ?? null);
        return $text !== null ? trim((string) $text) : null;
    }

    /** Распознавание текста с фото через Gemini (Google AI Studio). */
    private function runGeminiOcr(string $imagePath, string $apiKey): ?string
    {
        $content = file_get_contents($imagePath);
        if ($content === false || strlen($content) === 0) {
            return null;
        }
        $mime = 'image/jpeg';
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        }
        $base64 = base64_encode($content);
        if (strlen($base64) > 4 * 1024 * 1024) {
            $content = $this->resizeImageForVision($imagePath, $content);
            if ($content === null) {
                return null;
            }
            $base64 = base64_encode($content);
        }
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);
        $response = Http::timeout(25)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Извлеки весь текст с этого изображения разворота паспорта РФ. Верни только распознанный текст, построчно, без пояснений и комментариев. Если текст не разборчив — верни то, что удалось прочитать.',
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime,
                                    'data' => $base64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2048,
                ],
            ]);
        if (! $response->successful()) {
            $err = $response->json();
            $msg = $err['error']['message'] ?? $response->body();
            if (is_string($msg) && (stripos($msg, 'location') !== false || stripos($msg, 'not supported') !== false)) {
                $msg .= ' Используйте GOOGLE_VISION_API_KEY в .env (Google Cloud Console → Vision API) и удалите или оставьте пустым GEMINI_API_KEY.';
            }
            throw new \RuntimeException($msg);
        }
        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        return $text !== null ? trim((string) $text) : null;
    }

    /** Уменьшить изображение для Vision API (лимит ~4 MB). */
    private function resizeImageForVision(string $imagePath, string $content): ?string
    {
        $img = @imagecreatefromstring($content);
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $maxPx = 2000;
        if ($w <= $maxPx && $h <= $maxPx) {
            imagedestroy($img);
            return $content;
        }
        $scale = min($maxPx / $w, $maxPx / $h, 1.0);
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $scaled = imagescale($img, $nw, $nh);
        imagedestroy($img);
        if (! $scaled) {
            return null;
        }
        ob_start();
        imagejpeg($scaled, null, 85);
        $out = ob_get_clean();
        imagedestroy($scaled);
        return $out ?: null;
    }

    /** Убрать типичный мусор OCR и строки из одних символов. */
    private function cleanOcrPassportText(string $text): string
    {
        $text = preg_replace('/<{2,}/', '<', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($line) {
            if ($line === '') {
                return false;
            }
            $withoutSpaces = preg_replace('/\s/u', '', $line);
            $symbolOnly = preg_replace('/[\p{L}\p{N}]/u', '', $withoutSpaces);
            if (strlen($withoutSpaces) > 0 && strlen($symbolOnly) === strlen($withoutSpaces)) {
                return false;
            }
            return true;
        });
        $text = implode("\n", $lines);
        return trim($text);
    }

    /**
     * Извлечение полей паспорта через LLM (OpenAI или Deep Seek).
     * Возвращает ['ok' => true, 'fields' => [...]] при успехе или ['ok' => false, 'reason' => ..., 'message' => ...] при сбое.
     */
    private function parsePassportWithLlm(string $text): ?array
    {
        $openaiKey = config('services.openai.api_key');
        $deepseekKey = config('services.deepseek.api_key');
        if (($openaiKey === '' || $openaiKey === null) && ($deepseekKey === '' || $deepseekKey === null)) {
            return ['ok' => false, 'reason' => 'no_key', 'message' => 'Задайте OPENAI_API_KEY или DEEPSEEK_API_KEY в .env'];
        }
        if (strlen(trim($text)) < 10) {
            try {
                Log::info('OpenAI: пропуск — слишком короткий текст OCR');
            } catch (\Throwable $e) {
            }

            return ['ok' => false, 'reason' => 'short_text', 'message' => 'Слишком короткий текст с фото'];
        }

        $prompt = <<<PROMPT
Ниже приведён текст, распознанный с разворота паспорта РФ (OCR). Извлеки точные данные и верни ТОЛЬКО валидный JSON без комментариев и markdown, ровно с ключами:
- last_name: фамилия (ЗАГЛАВНЫМИ буквами)
- first_name: имя (ЗАГЛАВНЫМИ буквами)
- patronymic: отчество (ЗАГЛАВНЫМИ буквами)
- birth_date: дата рождения в формате ДД.ММ.ГГГГ или пустая строка
- passport_series_number: серия и номер паспорта (например "12 34 567890" или "1234567890")
- issued_by: кем выдан паспорт (полное название организации)
- issued_at: дата выдачи в формате ДД.ММ.ГГГГ или пустая строка

Если какое-то поле не найдено — верни для него пустую строку "". Не придумывай данные.

Текст паспорта:
---
{$text}
---
PROMPT;

        $useDeepSeek = ($openaiKey === '' || $openaiKey === null) && ($deepseekKey !== '' && $deepseekKey !== null);
        $apiKey = $useDeepSeek ? $deepseekKey : $openaiKey;
        $model = $useDeepSeek ? config('services.deepseek.model', 'deepseek-chat') : config('services.openai.model', 'gpt-4o-mini');
        $baseUrl = $useDeepSeek ? 'https://api.deepseek.com/v1' : 'https://api.openai.com/v1';
        $provider = $useDeepSeek ? 'Deep Seek' : 'OpenAI';

        try {
            $response = Http::timeout(15)
                ->withToken($apiKey)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ты извлекаешь структурированные данные из текста паспорта РФ. Отвечай только валидным JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            if (! $response->successful()) {
                try {
                    Log::warning($provider . ' API ошибка', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                } catch (\Throwable $e) {
                }
                $errBody = $response->json();
                $errMsg = $errBody['error']['message'] ?? $response->body();

                return ['ok' => false, 'reason' => 'api', 'message' => $provider . ': ' . (is_string($errMsg) ? $errMsg : json_encode($errMsg))];
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? null;
            if ($content === null || $content === '') {
                return ['ok' => false, 'reason' => 'empty', 'message' => 'Пустой ответ ' . $provider];
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return ['ok' => false, 'reason' => 'invalid_json', 'message' => $provider . ' вернул не JSON'];
            }

            return [
                'ok' => true,
                'provider' => $useDeepSeek ? 'deepseek' : 'openai',
                'fields' => $this->normalizeParsedFields([
                    'last_name' => $decoded['last_name'] ?? '',
                    'first_name' => $decoded['first_name'] ?? '',
                    'patronymic' => $decoded['patronymic'] ?? '',
                    'birth_date' => $decoded['birth_date'] ?? '',
                    'passport_series_number' => $decoded['passport_series_number'] ?? '',
                    'issued_by' => $decoded['issued_by'] ?? '',
                    'issued_at' => $decoded['issued_at'] ?? '',
                ]),
            ];
        } catch (\Throwable $e) {
            try {
                Log::warning($provider . ' исключение: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            } catch (\Throwable $logEx) {
            }

            return ['ok' => false, 'reason' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /** Приведение ФИО к виду «Первая заглавная, остальные строчные» (ИВАНОВ → Иванов). */
    private function nameToTitleCase(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_strtolower(mb_substr($name, 1));
    }

    /**
     * Парсинг текста паспорта РФ: извлечение ФИО, даты рождения, серии/номера, кем и когда выдан.
     */
    private function parsePassportFields(string $text): array
    {
        $lines = preg_split('/\n+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $lines = array_map('trim', $lines);
        $textFlat = preg_replace('/\s+/u', ' ', $text);

        $lastName = '';
        $firstName = '';
        $patronymic = '';
        $birthDate = '';
        $passportSeriesNumber = '';
        $issuedBy = '';
        $issuedAt = '';

        // ФИО в паспорте часто ЗАГЛАВНЫМИ; допускаем любые буквы и дефис
        $nameWord = '[А-ЯЁа-яё\-]+';

        // Фамилия — метка и значение на одной строке (в т.ч. "ФАМИЛИЯ ИВАНОВ" от OCR) (в т.ч. "ФАМИЛИЯ ИВАНОВ" от OCR)
        if (preg_match('/\bФамилия\b\s*[:\s]*(' . $nameWord . ')/ui', $textFlat, $m)) {
            $lastName = $this->nameToTitleCase(trim($m[1]));
        }
        // Метка на одной строке, значение на следующей
        foreach ($lines as $i => $line) {
            if (preg_match('/^[\d.]*\s*Фамилия\s*[:\s]*(' . $nameWord . ')/ui', $line, $m)) {
                $lastName = $this->nameToTitleCase(trim($m[1]));
                break;
            }
            if (preg_match('/Фамилия/ui', $line) && isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if (preg_match('/^' . $nameWord . '$/u', $next)) {
                    $lastName = $this->nameToTitleCase($next);
                    break;
                }
            }
        }
        if ($lastName !== '' && mb_strtoupper($lastName) === $lastName) {
            $lastName = $this->nameToTitleCase($lastName);
        }

        // Имя
        if (preg_match('/\bИмя\b\s*[:\s]*(' . $nameWord . ')/ui', $textFlat, $m)) {
            $firstName = $this->nameToTitleCase(trim($m[1]));
        }
        foreach ($lines as $i => $line) {
            if (preg_match('/^[\d.]*\s*Имя\s*[:\s]*(' . $nameWord . ')/ui', $line, $m)) {
                $firstName = $this->nameToTitleCase(trim($m[1]));
                break;
            }
            if (preg_match('/\bИмя\b/ui', $line) && isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if (preg_match('/^' . $nameWord . '$/u', $next)) {
                    $firstName = $this->nameToTitleCase($next);
                    break;
                }
            }
        }
        if ($firstName !== '' && mb_strtoupper($firstName) === $firstName) {
            $firstName = $this->nameToTitleCase($firstName);
        }

        // Отчество
        if (preg_match('/\bОтчество\b\s*[:\s]*(' . $nameWord . ')/ui', $textFlat, $m)) {
            $patronymic = $this->nameToTitleCase(trim($m[1]));
        }
        foreach ($lines as $i => $line) {
            if (preg_match('/^[\d.]*\s*Отчество\s*[:\s]*(' . $nameWord . ')/ui', $line, $m)) {
                $patronymic = $this->nameToTitleCase(trim($m[1]));
                break;
            }
            if (preg_match('/Отчество/ui', $line) && isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if (preg_match('/^' . $nameWord . '$/u', $next)) {
                    $patronymic = $this->nameToTitleCase($next);
                    break;
                }
            }
        }
        if ($patronymic !== '' && mb_strtoupper($patronymic) === $patronymic) {
            $patronymic = $this->nameToTitleCase($patronymic);
        }

        // Запасной вариант: первые 2–3 слова — ФИО (допускаем и ЗАГЛАВНЫЕ: ИВАНОВ ИВАН ИВАНОВИЧ)
        if ($lastName === '' && $firstName === '' && count($lines) > 0) {
            $words = [];
            foreach ($lines as $line) {
                foreach (preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                    $w = trim($w);
                    if ($w === '') {
                        continue;
                    }
                    $isNameLike = preg_match('/^[А-ЯЁ][а-яё\-]+$/u', $w) || preg_match('/^[А-ЯЁ\-]{2,}$/u', $w);
                    $isLabel = preg_match('/^(Фамилия|Имя|Отчество|Дата|Место|Пол|Кем|Когда|Орган|Серия|Номер|Паспорт)$/ui', $w);
                    if ($isNameLike && ! $isLabel) {
                        $words[] = $this->nameToTitleCase($w);
                        if (count($words) >= 3) {
                            break 2;
                        }
                    }
                }
            }
            if (count($words) >= 2) {
                $lastName = $words[0] ?? '';
                $firstName = $words[1] ?? '';
                $patronymic = $words[2] ?? '';
            }
        }

        // Дата рождения — DD.MM.YYYY или DD MM YYYY
        if (preg_match('/\bДата\s+рождения\b\s*[:\s]*(\d{1,2}[.\s]\d{1,2}[.\s]\d{2,4})/ui', $text, $m)) {
            $birthDate = preg_replace('/\s+/', '.', trim($m[1]));
            $birthDate = str_replace('..', '.', $birthDate);
        }
        if ($birthDate === '' && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $text, $m)) {
            $birthDate = $m[1];
        }

        // Серия и номер паспорта: 4 цифры + 6 цифр (с пробелами или без)
        if (preg_match('/\b(?:Серия|Паспорт|серия|номер)\s*[:\s]*(\d{2}\s?\d{2}\s?\d{6})/ui', $text, $m)) {
            $passportSeriesNumber = preg_replace('/\s+/', ' ', trim($m[1]));
        }
        if ($passportSeriesNumber === '' && preg_match('/(\d{2}\s?\d{2}\s?\d{6})/', $text, $m)) {
            $passportSeriesNumber = preg_replace('/\s+/', ' ', trim($m[1]));
        }

        // Кем выдан — после метки до следующей даты или конца блока
        if (preg_match('/\b(?:Кем\s+выдан|Орган[,\s]+выдавший|выдан)\s*[:\s]*([^.]+?)(?=\d{2}\.\d{2}\.\d{4}|$)/uis', $text, $m)) {
            $issuedBy = trim(preg_replace('/\s+/', ' ', $m[1]));
            $issuedBy = preg_replace('/^\s*[-–—]\s*/u', '', $issuedBy);
        }

        // Когда выдан / Дата выдачи
        if (preg_match('/\b(?:Дата\s+выдачи|Когда\s+выдан|выдан)\s*[:\s]*(\d{1,2}[.\s]\d{1,2}[.\s]\d{2,4})/ui', $text, $m)) {
            $issuedAt = preg_replace('/\s+/', '.', trim($m[1]));
        }
        if ($issuedAt === '' && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $text, $m)) {
            $issuedAt = $m[1];
        }

        // ФИО одной строкой (не разделяем фамилию/имя/отчество — в паспорте часто неоднозначно)
        $fio = trim(implode(' ', array_filter([$lastName, $firstName, $patronymic])));
        if ($fio === '') {
            foreach ($lines as $line) {
                $words = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY);
                if (count($words) >= 2 && count($words) <= 4) {
                    $allCyrillic = true;
                    foreach ($words as $w) {
                        if (! preg_match('/^[А-ЯЁа-яё\-]+$/u', $w) || preg_match('/^(Фамилия|Имя|Отчество|Дата|Место|Пол|Кем|Когда|Орган|Серия|Номер|Паспорт)$/ui', $w)) {
                            $allCyrillic = false;
                            break;
                        }
                    }
                    if ($allCyrillic) {
                        $lastName = $this->nameToTitleCase($words[0] ?? '');
                        $firstName = $this->nameToTitleCase($words[1] ?? '');
                        $patronymic = $this->nameToTitleCase($words[2] ?? '');
                        $fio = trim(implode(' ', array_filter([$lastName, $firstName, $patronymic])));
                        break;
                    }
                }
            }
        }

        return $this->normalizeParsedFields([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'patronymic' => $patronymic,
            'birth_date' => $birthDate,
            'passport_series_number' => $passportSeriesNumber,
            'issued_by' => $issuedBy,
            'issued_at' => $issuedAt,
        ]);
    }

    private function buildFullName(string $lastName, string $firstName, string $patronymic): string
    {
        return trim(implode(' ', array_filter([$lastName, $firstName, $patronymic])));
    }

    private function normalizeClientPassportData(Request $request): ?string
    {
        $text = trim((string) $request->input('client_passport', ''));
        if ($text !== '') {
            return $text;
        }
        $seriesNumber = trim((string) $request->input('client_passport_series_number', ''));
        $issuedBy = trim((string) $request->input('client_passport_issued_by', ''));
        $issuedAt = trim((string) $request->input('client_passport_issued_at', ''));
        $birthDate = trim((string) $request->input('client_birth_date', ''));
        $parts = array_filter([
            $seriesNumber !== '' ? 'Серия, номер: ' . $seriesNumber : null,
            $issuedBy !== '' ? 'Кем выдан: ' . $issuedBy : null,
            $issuedAt !== '' ? 'Когда выдан: ' . $issuedAt : null,
            $birthDate !== '' ? 'Дата рождения: ' . $birthDate : null,
        ]);
        if ($parts !== []) {
            return implode('. ', $parts);
        }

        return null;
    }

    private function findOrCreateClient(Request $request, string $lastName, string $firstName, string $patronymic, ?string $passportData): Client
    {
        $phone = preg_replace('/\D/', '', $request->client_phone);
        $client = Client::where('phone', $request->client_phone)->orWhere('phone', $phone)->first();
        $fullName = $this->buildFullName($lastName, $firstName, $patronymic);
        $data = [
            'last_name' => $lastName ?: null,
            'first_name' => $firstName ?: null,
            'patronymic' => $patronymic ?: null,
            'full_name' => $fullName,
            'email' => $request->client_email,
            'passport_data' => $passportData,
        ];
        if ($client) {
            $client->update($data);

            return $client;
        }

        return Client::create(array_merge($data, ['phone' => $request->client_phone]));
    }
}
