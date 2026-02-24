<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\DocumentLedgerTemplate;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Кассовые операции: приходные и расходные документы, баланс. */
class CashController extends Controller
{
    public function index(Request $request): View
    {
        $stores = Store::whereIn('id', auth()->user()->allowedStoreIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $storeId = $request->get('store_id');
        if (! $storeId || ! in_array((int) $storeId, auth()->user()->allowedStoreIds(), true)) {
            $storeId = $stores->first()?->id;
        }

        $query = CashDocument::with(['operationType', 'createdByUser', 'client', 'store', 'targetStore'])
            ->whereIn('store_id', auth()->user()->allowedStoreIds());

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        } elseif ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId)->orWhere('target_store_id', $storeId);
            });
        }
        if ($request->filled('direction')) {
            $query->whereHas('operationType', fn ($q) => $q->where('direction', $request->direction));
        }
        if ($request->filled('date_from')) {
            $query->where('document_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('document_date', '<=', $request->date_to);
        }

        $documents = $query->orderByDesc('document_date')->orderByDesc('id')->paginate(30)->withQueryString();

        $store = $storeId ? Store::find($storeId) : null;
        $balance = $store ? $store->cash_balance : 0;
        $filterClient = $request->filled('client_id') ? \App\Models\Client::find($request->client_id) : null;
        $clientBalance = $filterClient ? $filterClient->cash_balance : null;

        return view('cash.index', compact('stores', 'store', 'documents', 'balance', 'filterClient', 'clientBalance'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! auth()->user()->canProcessSales()) {
            abort(403, 'Нет прав на кассовые операции.');
        }

        $stores = Store::whereIn('id', auth()->user()->allowedStoreIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($stores->isEmpty()) {
            return redirect()->route('cash.index')->with('error', 'Нет доступных магазинов.');
        }

        $storeId = $request->get('store_id', $stores->first()->id);
        if (! in_array((int) $storeId, auth()->user()->allowedStoreIds(), true)) {
            $storeId = $stores->first()->id;
        }

        $incomeTypes = CashOperationType::incomeTypes();
        $expenseTypes = CashOperationType::expenseTypes();
        $transferType = CashOperationType::findByName('Перемещение между кассами');

        return view('cash.create', compact('stores', 'storeId', 'incomeTypes', 'expenseTypes', 'transferType'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->canProcessSales()) {
            abort(403);
        }

        $transferType = CashOperationType::findByName('Перемещение между кассами');
        $isTransfer = $transferType && (int) $request->operation_type_id === $transferType->id;

        $rules = [
            'store_id' => 'required|exists:stores,id',
            'target_store_id' => ($isTransfer ? 'required|' : 'nullable|') . 'exists:stores,id|different:store_id',
            'client_id' => 'nullable|exists:clients,id',
            'operation_type_id' => 'required|exists:cash_operation_types,id',
            'document_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:1000',
        ];
        $validated = $request->validate($rules);

        if (! in_array((int) $validated['store_id'], auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if (! empty($validated['target_store_id']) && ! in_array((int) $validated['target_store_id'], auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $operationType = CashOperationType::findOrFail($validated['operation_type_id']);
        $documentNumber = CashDocument::generateDocumentNumber(
            (int) $validated['store_id'],
            $operationType->direction
        );

        $doc = CashDocument::create([
            'store_id' => $validated['store_id'],
            'target_store_id' => $validated['target_store_id'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'operation_type_id' => $validated['operation_type_id'],
            'document_number' => $documentNumber,
            'document_date' => $validated['document_date'],
            'amount' => $validated['amount'],
            'comment' => $validated['comment'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $ledger = app(LedgerService::class);
        $amount = (float) $validated['amount'];
        $entryDate = \Carbon\Carbon::parse($validated['document_date']);
        $comment = ($documentNumber . ($validated['comment'] ?? '')) ?: null;

        $clientId = $doc->client_id;
        if ($operationType->name === 'Перемещение между кассами' && ! empty($validated['target_store_id'])) {
            $ledger->postTransfer($amount, $entryDate, (int) $validated['store_id'], (int) $validated['target_store_id'], 'cash_document', $doc->id, $comment, $clientId);
        } elseif ($operationType->isIncome()) {
            $ledger->post(Account::CODE_CASH, Account::CODE_SETTLEMENTS_OTHER, $amount, $entryDate, (int) $validated['store_id'], 'cash_document', $doc->id, $comment, $clientId);
        } else {
            // Оплата продавцу: Дт 60 Кт 50; прочие расходы: Дт 76 Кт 50
            $debitAccount = $operationType->name === 'Оплата продавцу' ? Account::CODE_SUPPLIERS : Account::CODE_SETTLEMENTS_OTHER;
            $ledger->post($debitAccount, Account::CODE_CASH, $amount, $entryDate, (int) $validated['store_id'], 'cash_document', $doc->id, $comment, $clientId);
        }

        $label = $operationType->name === 'Перемещение между кассами'
            ? 'Перемещение'
            : ($operationType->isIncome() ? 'Приход' : 'Расход');

        return redirect()->route('cash.index', ['store_id' => $validated['store_id']])
            ->with('success', "Документ {$label} №{$documentNumber} создан.");
    }

    public function show(CashDocument $cashDocument): View
    {
        if (! in_array($cashDocument->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $cashDocument->load(['operationType', 'store', 'targetStore', 'client', 'createdByUser']);

        $ledgerEntries = LedgerEntry::where('document_type', 'cash_document')
            ->where('document_id', $cashDocument->id)
            ->with('account')
            ->orderBy('id')
            ->get();
        $templates = DocumentLedgerTemplate::forDocumentType('cash_document');

        return view('cash.show', compact(
            'cashDocument',
            'ledgerEntries',
            'templates'
        ));
    }

    public function edit(CashDocument $cashDocument): View|RedirectResponse
    {
        if (! auth()->user()->canProcessSales()) {
            abort(403);
        }
        if (! in_array($cashDocument->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $stores = Store::whereIn('id', auth()->user()->allowedStoreIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $incomeTypes = CashOperationType::incomeTypes();
        $expenseTypes = CashOperationType::expenseTypes();
        $transferType = CashOperationType::findByName('Перемещение между кассами');

        return view('cash.edit', compact('cashDocument', 'stores', 'incomeTypes', 'expenseTypes', 'transferType'));
    }

    public function update(Request $request, CashDocument $cashDocument): RedirectResponse
    {
        if (! auth()->user()->canProcessSales()) {
            abort(403);
        }
        if (! in_array($cashDocument->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $transferType = CashOperationType::findByName('Перемещение между кассами');
        $isTransfer = $transferType && (int) $request->operation_type_id === $transferType->id;

        $rules = [
            'store_id' => 'required|exists:stores,id',
            'target_store_id' => ($isTransfer ? 'required|' : 'nullable|') . 'exists:stores,id|different:store_id',
            'client_id' => 'nullable|exists:clients,id',
            'operation_type_id' => 'required|exists:cash_operation_types,id',
            'document_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:1000',
        ];
        $validated = $request->validate($rules);

        if (! in_array((int) $validated['store_id'], auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if (! empty($validated['target_store_id']) && ! in_array((int) $validated['target_store_id'], auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        LedgerEntry::where('document_type', 'cash_document')->where('document_id', $cashDocument->id)->delete();

        $cashDocument->update([
            'store_id' => $validated['store_id'],
            'target_store_id' => $validated['target_store_id'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'operation_type_id' => $validated['operation_type_id'],
            'document_date' => $validated['document_date'],
            'amount' => $validated['amount'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $operationType = CashOperationType::findOrFail($validated['operation_type_id']);
        $amount = (float) $validated['amount'];
        $entryDate = \Carbon\Carbon::parse($validated['document_date']);
        $comment = ($cashDocument->document_number . ($validated['comment'] ?? '')) ?: null;
        $clientId = $cashDocument->client_id;
        $ledger = app(LedgerService::class);

        if ($operationType->name === 'Перемещение между кассами' && ! empty($validated['target_store_id'])) {
            $ledger->postTransfer($amount, $entryDate, (int) $validated['store_id'], (int) $validated['target_store_id'], 'cash_document', $cashDocument->id, $comment, $clientId);
        } elseif ($operationType->isIncome()) {
            $ledger->post(Account::CODE_CASH, Account::CODE_SETTLEMENTS_OTHER, $amount, $entryDate, (int) $validated['store_id'], 'cash_document', $cashDocument->id, $comment, $clientId);
        } else {
            // Оплата продавцу: Дт 60 Кт 50; прочие расходы: Дт 76 Кт 50
            $debitAccount = $operationType->name === 'Оплата продавцу' ? Account::CODE_SUPPLIERS : Account::CODE_SETTLEMENTS_OTHER;
            $ledger->post($debitAccount, Account::CODE_CASH, $amount, $entryDate, (int) $validated['store_id'], 'cash_document', $cashDocument->id, $comment, $clientId);
        }

        return redirect()->route('cash.show', $cashDocument)->with('success', 'Документ сохранён. Проводки пересозданы.');
    }

    public function destroy(CashDocument $cashDocument): RedirectResponse
    {
        if (! auth()->user()->canProcessSales()) {
            abort(403);
        }
        if (! in_array($cashDocument->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $storeId = $cashDocument->store_id;
        $cashDocument->delete();

        return redirect()->route('cash.index', ['store_id' => $storeId])
            ->with('success', 'Документ удалён.');
    }

    /** Отчёт по всем кассам. */
    public function report(Request $request): View
    {
        $stores = Store::whereIn('id', auth()->user()->allowedStoreIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $totals = [];
        $grandTotal = 0;
        foreach ($stores as $s) {
            $totals[$s->id] = $s->cash_balance;
            $grandTotal += $totals[$s->id];
        }

        return view('cash.report', compact('stores', 'totals', 'grandTotal'));
    }
}
