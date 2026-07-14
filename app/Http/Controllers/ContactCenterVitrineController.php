<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Store;
use App\Services\ContactCenter\CommissionVitrinePriorityService;
use App\Services\Avito\AvitoInboxSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Приоритетный список комиссионных товаров на витрине для колл-центра. */
class ContactCenterVitrineController extends Controller
{
    public function index(Request $request, CommissionVitrinePriorityService $service): View
    {
        $user = Auth::user();
        $data = $service->buildList($user->allowedStoreIds(), [
            'stale_only' => $request->boolean('stale_only'),
            'store_id' => $request->filled('store_id') ? $request->integer('store_id') : null,
            'search' => $request->string('search')->toString(),
        ]);

        return view('contact-center.vitrine-priority.index', [
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'staleDays' => $data['stale_days'],
            'stores' => Store::query()->where('is_active', true)->orderBy('name')->get(),
            'canDiscount' => $user->canApplyVitrineDiscount(),
        ]);
    }

    public function applyDiscount(Request $request, Item $item, CommissionVitrinePriorityService $service): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->canApplyVitrineDiscount()) {
            abort(403);
        }
        if (! in_array($item->store_id, $user->allowedStoreIds(), true)) {
            abort(403);
        }
        if (! $item->commissionContract || $item->commissionContract->is_sold) {
            return back()->with('error', 'Скидку можно назначить только на непроданный комиссионный товар.');
        }

        $validated = $request->validate([
            'new_price' => ['required', 'numeric', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newPrice = round((float) $validated['new_price'], 2);
        $oldPrice = $item->current_price !== null ? (float) $item->current_price : null;

        if ($oldPrice !== null && $newPrice >= $oldPrice) {
            return back()->with('error', 'Новая цена должна быть ниже текущей (скидка).');
        }

        $service->applyDiscount(
            $item,
            $newPrice,
            $validated['reason'] ?? 'Скидка из контакт-центра',
            $user->id,
        );

        return back()->with('success', sprintf(
            'Цена «%s» снижена: %s → %s ₽',
            $item->name,
            $oldPrice !== null ? number_format($oldPrice, 0, ',', ' ') : '—',
            number_format($newPrice, 0, ',', ' '),
        ));
    }

    public function syncAvitoInbox(AvitoInboxSyncService $sync): RedirectResponse
    {
        set_time_limit(600);

        $result = $sync->syncActiveListingInquiries();
        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Не удалось синхронизировать Avito.');
        }

        $totals = $result['totals'] ?? [];

        return back()->with('success', sprintf(
            'Avito: загружено %d чатов по %d активным объявлениям, %d сообщений.',
            (int) ($totals['chats_synced'] ?? 0),
            (int) ($totals['active_listings'] ?? 0),
            (int) ($totals['messages_ingested'] ?? 0),
        ));
    }
}
