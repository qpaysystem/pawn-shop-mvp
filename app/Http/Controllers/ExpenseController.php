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
        $entryDate = $expense->expense_date;
        $storeId = $expense->store_id;
        $docType = 'expense';
        $docId = $expense->id;
        $clientId = $expense->client_id;

        if ($clientId) {
            // Привязка к клиенту: Дт 62 (долг клиента) Кт 50 — возникает дебиторская задолженность
            $ledger->post(
                Account::CODE_BUYERS,
                Account::CODE_CASH,
                $amount,
                $entryDate,
                $storeId,
                $docType,
                $docId,
                $comment,
                $clientId
            );
        } else {
            // Без клиента: Дт счёт вида расхода Кт 50 (касса)
            $debitAccount = $expense->expenseType->account;
            $debitCode = $debitAccount ? $debitAccount->code : Account::CODE_OTHER_INCOME;
            $ledger->post(
                $debitCode,
                Account::CODE_CASH,
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
