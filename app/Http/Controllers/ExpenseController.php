<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\DocumentLedgerTemplate;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Документы начисления расходов. */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['expenseType', 'store', 'client', 'createdByUser'])->orderBy('expense_date', 'desc')->orderBy('id', 'desc');
        if ($request->filled('expense_type_id')) {
            $query->where('expense_type_id', $request->expense_type_id);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        $expenses = $query->paginate(20)->withQueryString();
        $expenseTypes = ExpenseType::where('is_active', true)->orderBy('sort_order')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $clients = Client::orderBy('last_name')->orderBy('first_name')->get();
        return view('expenses.index', compact('expenses', 'expenseTypes', 'stores', 'clients'));
    }

    public function create()
    {
        $expenseTypes = ExpenseType::where('is_active', true)->orderBy('sort_order')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $clients = Client::orderBy('last_name')->orderBy('first_name')->get();
        return view('expenses.create', compact('expenseTypes', 'stores', 'clients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_type_id' => 'required|exists:expense_types,id',
            'store_id' => 'nullable|exists:stores,id',
            'client_id' => 'nullable|exists:clients,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ]);
        $data['created_by'] = auth()->id();
        $last = Expense::orderBy('id', 'desc')->first();
        $data['number'] = 'РД-' . (($last ? $last->id + 1 : 1));
        $expense = Expense::create($data);
        $expense->load(['expenseType.account', 'client']);
        $comment = $expense->number . ': ' . ($expense->expenseType->name ?? '');
        if ($expense->client) {
            $comment .= ' — ' . $expense->client->full_name;
        }
        if ($expense->description) {
            $comment .= ' — ' . \Illuminate\Support\Str::limit($expense->description, 80);
        }
        $ledger = app(LedgerService::class);
        $amount = (float) $expense->amount;
        $entryDate = Carbon::parse($expense->expense_date);
        $storeId = $expense->store_id;
        $docType = 'expense';
        $docId = $expense->id;
        $clientId = $expense->client_id;

        // Документ расхода — начисление за оказанные услуги (не кассовый). Формирует долг клиента и увеличение расходов на счёте 44.
        if ($clientId) {
            // Дт 62 Кт 90 — дебиторская задолженность клиента (клиент должен нам), выручка от услуг
            $ledger->post(
                Account::CODE_BUYERS,
                Account::CODE_SALES,
                $amount,
                $entryDate,
                $storeId,
                $docType,
                $docId,
                $comment,
                $clientId
            );
            // Дт 44 Кт 76 — увеличение расходов на продажу (счёт 44)
            $ledger->post(
                Account::CODE_SELLING_EXPENSES,
                Account::CODE_SETTLEMENTS_OTHER,
                $amount,
                $entryDate,
                $storeId,
                $docType,
                $docId,
                $comment,
                null
            );
        } else {
            // Без клиента: только начисление расхода на 44 — Дт 44 Кт 76
            $ledger->post(
                Account::CODE_SELLING_EXPENSES,
                Account::CODE_SETTLEMENTS_OTHER,
                $amount,
                $entryDate,
                $storeId,
                $docType,
                $docId,
                $comment,
                null
            );
        }
        return redirect()->route('expenses.index')->with('success', 'Расход начислен.');
    }

    public function show(Expense $expense)
    {
        if ($expense->store_id && ! in_array($expense->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $expense->load(['expenseType', 'store', 'client', 'createdByUser']);

        $ledgerEntries = LedgerEntry::where('document_type', 'expense')
            ->where('document_id', $expense->id)
            ->with('account')
            ->orderBy('id')
            ->get();
        $templates = DocumentLedgerTemplate::forDocumentType('expense');
        $documentType = 'expense';
        $documentId = $expense->id;

        return view('expenses.show', compact(
            'expense', 'ledgerEntries', 'templates', 'documentType', 'documentId'
        ));
    }
}
