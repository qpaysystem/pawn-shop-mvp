<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

/** CRUD магазинов (доступ: super-admin). */
class StoreController extends Controller
{
    public function index()
    {
        $this->authorizeStoreManagement();
        $stores = Store::orderBy('name')->paginate(20);

        return view('stores.index', compact('stores'));
    }

    public function create()
    {
        $this->authorizeStoreManagement();
        return view('stores.create');
    }

    public function store(Request $request)
    {
        $this->authorizeStoreManagement();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        Store::create($data);

        return redirect()->route('stores.index')->with('success', 'Магазин создан.');
    }

    public function edit(Store $store)
    {
        $this->authorizeStoreManagement();
        return view('stores.edit', compact('store'));
    }

    public function update(Request $request, Store $store)
    {
        $this->authorizeStoreManagement();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $store->update($data);

        return redirect()->route('stores.index')->with('success', 'Магазин обновлён.');
    }

    public function destroy(Store $store)
    {
        $this->authorizeStoreManagement();
        $store->delete();

        return redirect()->route('stores.index')->with('success', 'Магазин удалён.');
    }

    private function authorizeStoreManagement(): void
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Только супер-администратор может управлять магазинами.');
        }
    }
}
