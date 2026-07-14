<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ItemCategory;
use App\Models\ItemStatus;
use App\Models\StorageLocation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile v1: справочники (те же запросы, что AcceptItemController::create). */
class CatalogController extends Controller
{
    public function stores(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $stores = Store::query()
            ->whereIn('id', $user->allowedStoreIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'phone', 'is_active']);

        return response()->json($stores);
    }

    public function itemCategories(): JsonResponse
    {
        $categories = ItemCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return response()->json($categories);
    }

    public function brands(): JsonResponse
    {
        $brands = Brand::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($brands);
    }

    public function itemStatuses(): JsonResponse
    {
        $statuses = ItemStatus::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json($statuses);
    }

    public function storageLocations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $storeId = $request->integer('store_id');

        if (! $storeId || ! in_array($storeId, $user->allowedStoreIds(), true)) {
            return response()->json(['message' => 'Invalid store_id'], 422);
        }

        $locations = StorageLocation::query()
            ->where('store_id', $storeId)
            ->orderBy('name')
            ->get(['id', 'name', 'store_id']);

        return response()->json($locations);
    }
}
