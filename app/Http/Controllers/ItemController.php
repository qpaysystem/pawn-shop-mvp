<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** CRUD товаров + смена статуса/места хранения. */
class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with(['store', 'status', 'storageLocation', 'category', 'brand']);
        $storeIds = Auth::user()->allowedStoreIds();
        $query->whereIn('store_id', $storeIds);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }
        $items = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $statuses = ItemStatus::orderBy('name')->get();

        return view('items.index', compact('items', 'statuses'));
    }

    public function show(Item $item)
    {
        if (! in_array($item->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $item->load(['store', 'status', 'storageLocation', 'category', 'brand', 'statusHistory.newStatus', 'statusHistory.oldStatus', 'statusHistory.changedByUser']);
        $item->load(['pawnContract.client', 'pawnContract.appraiser', 'commissionContract.client', 'commissionContract.appraiser']);

        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        if (! in_array($item->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $statuses = ItemStatus::orderBy('name')->get();
        $locations = StorageLocation::where('store_id', $item->store_id)->orderBy('name')->get();

        return view('items.edit', compact('item', 'statuses', 'locations'));
    }

    public function update(Request $request, Item $item)
    {
        if (! in_array($item->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $user = Auth::user();
        if (! $user->canManageStorage()) {
            abort(403, 'Нет прав на изменение статуса/места хранения.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'current_price' => 'nullable|numeric|min:0',
            'status_id' => 'nullable|exists:item_statuses,id',
            'storage_location_id' => 'nullable|exists:storage_locations,id',
        ]);

        $oldStatusId = $item->status_id;
        $item->update($data);

        if (array_key_exists('status_id', $data) && (int) $data['status_id'] !== (int) $oldStatusId) {
            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $data['status_id'],
                'changed_by' => $user->id,
            ]);
        }

        return redirect()->route('items.show', $item)->with('success', 'Товар обновлён.');
    }

    public function destroy(Item $item)
    {
        if (! in_array($item->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Товар удалён.');
    }
}
