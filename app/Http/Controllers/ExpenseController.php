<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Store;
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
        Expense::create($data);
        return redirect()->route('expenses.index')->with('success', 'Расход начислен.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['expenseType', 'store', 'createdByUser']);
        return view('expenses.show', compact('expense'));
    }
}
