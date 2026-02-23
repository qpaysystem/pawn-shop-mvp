<?php

namespace App\Http\Controllers;

use App\Models\ItemStatus;
use Illuminate\Http\Request;

/** CRUD статусов товара. */
class ItemStatusController extends Controller
{
    public function index()
    {
        $statuses = ItemStatus::orderBy('name')->paginate(20);

        return view('item-statuses.index', compact('statuses'));
    }

    public function create()
    {
        return view('item-statuses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);
        ItemStatus::create($data);

        return redirect()->route('item-statuses.index')->with('success', 'Статус создан.');
    }

    public function edit(ItemStatus $itemStatus)
    {
        return view('item-statuses.edit', compact('itemStatus'));
    }

    public function update(Request $request, ItemStatus $itemStatus)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);
        $itemStatus->update($data);

        return redirect()->route('item-statuses.index')->with('success', 'Статус обновлён.');
    }

    public function destroy(ItemStatus $itemStatus)
    {
        $itemStatus->delete();

        return redirect()->route('item-statuses.index')->with('success', 'Статус удалён.');
    }
}
