<?php

namespace App\Http\Controllers;

use App\Models\CommissionContract;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

/**
 * Дашборд после входа: виджеты по магазину пользователя.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $storeIds = $user->allowedStoreIds();

        $itemsCount = Item::whereIn('store_id', $storeIds)->count();
        $activePawnCount = PawnContract::whereIn('store_id', $storeIds)->where('is_redeemed', false)->count();
        $activeCommissionCount = CommissionContract::whereIn('store_id', $storeIds)->where('is_sold', false)->count();

        // Активные договоры залога для вкладки «Сделать выкуп»
        $activePawnForRedeem = PawnContract::whereIn('store_id', $storeIds)
            ->where('is_redeemed', false)
            ->with(['client', 'item'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $totalCashBalance = 0;
        if ($user->canProcessSales()) {
            $stores = Store::whereIn('id', $storeIds)->get();
            foreach ($stores as $s) {
                $totalCashBalance += $s->cash_balance;
            }
        }

        return view('dashboard', compact('itemsCount', 'activePawnCount', 'activeCommissionCount', 'activePawnForRedeem', 'totalCashBalance'));
    }
}
