<?php

namespace App\Http\Controllers;

use App\Models\Account;
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
        $query = Expense::with(['expenseType', 'store', 'createdByUser'])->orderBy('expense_date', 'desc')->orderBy('id', 'desc');
        if ($request->filled('expense_type_id')) {
            $query->where('expense_type_id', $request->expense_type_id);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        $expenses = $query->paginate(20)->withQueryString();
        $expenseTypes = ExpenseType::where('is_active', true)->orderBy('sort_order')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('expenses.index', compact('expenses', 'expenseTypes', 'stores'));
    }

    public function create()
    {
        $expenseTypes = ExpenseType::where('is_active', true)->orderBy('sort_order')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('expenses.create', compact('expenseTypes', 'stores'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_type_id' => 'required|exists:expense_types,id',
            'store_id' => 'nullable|exists:stores,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ]);
        $data['created_by'] = auth()->id();
        $last = Expense::orderBy('id', 'desc')->first();
        $data['number'] = 'РД-' . (($last ? $last->id + 1 : 1));
        $expense = Expense::create($data);
        $expense->load('expenseType.account');
        $debitAccount = $expense->expenseType->account;
        $debitCode = $debitAccount ? $debitAccount->code : Account::CODE_OTHER_INCOME;
        $comment = $expense->number . ': ' . ($expense->expenseType->name ?? '');
        if ($expense->description) {
            $comment .= ' — ' . \Illuminate\Support\Str::limit($expense->description, 100);
        }
        app(LedgerService::class)->post(
            $debitCode,
            Account::CODE_CASH,
            (float) $expense->amount,
            $expense->expense_date,
            $expense->store_id,
            'expense',
            $expense->id,
            $comment,
            null
        );
        return redirect()->route('expenses.index')->with('success', 'Расход начислен.');
    }

    public function show(Expense $expense)
    {
        if ($expense->store_id && ! in_array($expense->store_id, auth()->user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $expense->load(['expenseType', 'store', 'createdByUser']);

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
