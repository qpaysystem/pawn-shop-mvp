<?php

namespace App\Http\Controllers;

use App\Models\StorageLocation;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** CRUD мест хранения (в рамках доступных магазинов). */
class StorageLocationController extends Controller
{
    public function index(Request $request)
    {
        $query = StorageLocation::with('store');
        $storeIds = Auth::user()->allowedStoreIds();
        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        $locations = $query->orderBy('name')->paginate(20);
        $stores = Store::whereIn('id', $storeIds)->orderBy('name')->get();

        return view('storage-locations.index', compact('locations', 'stores'));
    }

    public function create()
    {
        $stores = Store::whereIn('id', Auth::user()->allowedStoreIds())->orderBy('name')->get();

        return view('storage-locations.create', compact('stores'));
    }

    public function store(Request $request)
    {
        $storeIds = Auth::user()->allowedStoreIds();
        $data = $request->validate([
            'store_id' => 'required|exists:stores,id|in:' . implode(',', $storeIds),
            'name' => 'required|string|max:255',
        ]);
        StorageLocation::create($data);

        return redirect()->route('storage-locations.index')->with('success', 'Место хранения создано.');
    }

    public function edit(StorageLocation $storageLocation)
    {
        if (! in_array($storageLocation->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $stores = Store::whereIn('id', Auth::user()->allowedStoreIds())->orderBy('name')->get();

        return view('storage-locations.edit', compact('storageLocation', 'stores'));
    }

    public function update(Request $request, StorageLocation $storageLocation)
    {
        if (! in_array($storageLocation->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $storeIds = Auth::user()->allowedStoreIds();
        $data = $request->validate([
            'store_id' => 'required|exists:stores,id|in:' . implode(',', $storeIds),
            'name' => 'required|string|max:255',
        ]);
        $storageLocation->update($data);

        return redirect()->route('storage-locations.index')->with('success', 'Место хранения обновлено.');
    }

    public function destroy(StorageLocation $storageLocation)
    {
        if (! in_array($storageLocation->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $storageLocation->delete();

        return redirect()->route('storage-locations.index')->with('success', 'Место хранения удалено.');
    }
}
