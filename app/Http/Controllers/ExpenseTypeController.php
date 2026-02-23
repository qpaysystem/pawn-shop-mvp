<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseType;
use Illuminate\Http\Request;

/** Справочник видов расходов. */
class ExpenseTypeController extends Controller
{
    public function index()
    {
        $expenseTypes = ExpenseType::with('account')->orderBy('sort_order')->orderBy('name')->get();
        return view('expense-types.index', compact('expenseTypes'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        return view('expense-types.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'account_id' => 'nullable|exists:accounts,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['is_active'] = true;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        ExpenseType::create($data);
        return redirect()->route('expense-types.index')->with('success', 'Вид расхода создан.');
    }

    public function edit(ExpenseType $expenseType)
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        return view('expense-types.edit', compact('expenseType', 'accounts'));
    }

    public function update(Request $request, ExpenseType $expenseType)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'account_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $expenseType->update($data);
        return redirect()->route('expense-types.index')->with('success', 'Вид расхода обновлён.');
    }

    public function destroy(ExpenseType $expenseType)
    {
        if ($expenseType->expenses()->exists()) {
            return redirect()->route('expense-types.index')->with('error', 'Нельзя удалить вид расхода, по которому есть документы.');
        }
        $expenseType->delete();
        return redirect()->route('expense-types.index')->with('success', 'Вид расхода удалён.');
    }
}
