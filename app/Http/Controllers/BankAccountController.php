<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Store;
use Illuminate\Http\Request;

/** Расчётные счета банка. */
class BankAccountController extends Controller
{
    public function index()
    {
        $bankAccounts = BankAccount::with('store')->orderBy('sort_order')->orderBy('name')->get();
        return view('bank-accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('bank-accounts.create', compact('stores'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'bik' => 'nullable|string|max:20',
            'correspondent_account' => 'nullable|string|max:50',
            'store_id' => 'nullable|exists:stores,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        BankAccount::create($data);
        return redirect()->route('bank-accounts.index')->with('success', 'Расчётный счёт добавлен.');
    }

    public function edit(BankAccount $bankAccount)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('bank-accounts.edit', compact('bankAccount', 'stores'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'bik' => 'nullable|string|max:20',
            'correspondent_account' => 'nullable|string|max:50',
            'store_id' => 'nullable|exists:stores,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $bankAccount->update($data);
        return redirect()->route('bank-accounts.index')->with('success', 'Расчётный счёт обновлён.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->bankStatements()->exists()) {
            return redirect()->route('bank-accounts.index')->with('error', 'Нельзя удалить счёт, по которому есть выписки.');
        }
        $bankAccount->delete();
        return redirect()->route('bank-accounts.index')->with('success', 'Расчётный счёт удалён.');
    }
}
