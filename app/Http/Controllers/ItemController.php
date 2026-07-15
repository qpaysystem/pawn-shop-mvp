<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\LmbProductEvent;
use App\Models\StorageLocation;
use App\Services\ContactCenter\ItemReservationService;
use App\Services\Items\ItemAvitoLifeService;
use Carbon\Carbon;
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

    public function show(Item $item, ItemReservationService $reservations, ItemAvitoLifeService $avitoLife)
    {
        if (! in_array($item->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $item->load([
            'store', 'status', 'storageLocation', 'category', 'brand',
            'statusHistory.newStatus', 'statusHistory.oldStatus', 'statusHistory.changedByUser',
            'pawnContract.client', 'pawnContract.appraiser',
            'commissionContract.client', 'commissionContract.appraiser',
            'purchaseContract.client',
            'reservations.lead', 'reservations.client', 'reservations.createdByUser',
            'contactCenterLeads.createdByUser',
        ]);

        $activeReservation = $reservations->activeForItem($item);
        $avitoSummary = $avitoLife->summaryForItem($item);
        $lifeMap = $this->buildLifeMap($item, $avitoLife);

        return view('items.show', compact('item', 'activeReservation', 'lifeMap', 'avitoSummary'));
    }

    /** @return array<int, array{at: \Carbon\Carbon, kind: string, title: string, meta: ?string, url: ?string}> */
    private function buildLifeMap(Item $item, ItemAvitoLifeService $avitoLife): array
    {
        $events = [];

        foreach ($item->statusHistory as $h) {
            $events[] = [
                'at' => Carbon::parse($h->created_at),
                'kind' => 'status',
                'title' => 'Статус: '.($h->oldStatus?->name ?? '—').' → '.($h->newStatus?->name ?? '—'),
                'meta' => $h->changedByUser?->name,
            ];
        }

        foreach ($item->reservations as $r) {
            $events[] = [
                'at' => Carbon::parse($r->created_at),
                'kind' => 'reservation',
                'title' => 'Бронь ('.$r->statusLabel().') до '.$r->reserved_until->format('d.m.Y'),
                'meta' => trim(($r->client?->full_name ?? $r->contact_name ?? '').' '.($r->lead?->lead_number ? '· '.$r->lead->lead_number : '')),
            ];
        }

        foreach ($item->contactCenterLeads as $lead) {
            $events[] = [
                'at' => Carbon::parse($lead->created_at),
                'kind' => 'lead',
                'title' => 'Заявка '.$lead->lead_number.' — '.$lead->typeLabel(),
                'meta' => $lead->statusLabel().($lead->createdByUser ? ' · '.$lead->createdByUser->name : ''),
            ];
        }

        if ($item->pawnContract?->created_at) {
            $events[] = [
                'at' => Carbon::parse($item->pawnContract->created_at),
                'kind' => 'contract',
                'title' => 'Договор залога №'.$item->pawnContract->contract_number,
                'meta' => $item->pawnContract->client?->full_name,
            ];
        }
        if ($item->commissionContract?->created_at) {
            $events[] = [
                'at' => Carbon::parse($item->commissionContract->created_at),
                'kind' => 'contract',
                'title' => 'Договор комиссии №'.$item->commissionContract->contract_number,
                'meta' => $item->commissionContract->client?->full_name,
            ];
        }
        if ($item->purchaseContract?->created_at) {
            $events[] = [
                'at' => Carbon::parse($item->purchaseContract->created_at),
                'kind' => 'contract',
                'title' => 'Договор скупки №'.$item->purchaseContract->contract_number,
                'meta' => $item->purchaseContract->client?->full_name,
            ];
        }

        foreach ($avitoLife->lifeMapEventsForItem($item) as $event) {
            $events[] = $event;
        }

        foreach (LmbProductEvent::query()->where('item_id', $item->id)->orderByDesc('event_at')->get() as $pe) {
            $from = $pe->fromStore?->name;
            $to = $pe->toStore?->name;
            $title = $pe->typeLabel();
            if (in_array($pe->event_type, [LmbProductEvent::TYPE_MOVE, LmbProductEvent::TYPE_MOVE_PENDING], true)) {
                $title .= ': '.($from ?? '—').' → '.($to ?? '—');
            } elseif ($pe->event_type === LmbProductEvent::TYPE_STATUS || $pe->status_name) {
                $title .= ($pe->status?->name || $pe->status_name)
                    ? ': '.($pe->status?->name ?? $pe->status_name)
                    : '';
            } elseif ($pe->description) {
                $title .= ': '.mb_strimwidth($pe->description, 0, 80, '…');
            }
            $kind = match (true) {
                in_array($pe->event_type, [LmbProductEvent::TYPE_MOVE, LmbProductEvent::TYPE_MOVE_PENDING], true) => 'move',
                $pe->event_type === LmbProductEvent::TYPE_STATUS => 'status',
                in_array($pe->event_type, ['Выкуп', 'Поступление залог', 'Поступление перезалог', 'Поступление скупка', 'Продажа'], true) => 'contract',
                default => 'lmb_event',
            };
            $events[] = [
                'at' => $pe->event_at ? Carbon::parse($pe->event_at) : Carbon::parse($pe->created_at),
                'kind' => $kind,
                'title' => $title,
                'meta' => trim(implode(' · ', array_filter([
                    $pe->event_number ? '№'.$pe->event_number : null,
                    $pe->responsible,
                    $pe->source_doc_ref,
                    ($kind === 'move' || $kind === 'status') && ! $pe->applied ? 'без применения к карточке' : null,
                ]))),
            ];
        }

        usort($events, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return $events;
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
