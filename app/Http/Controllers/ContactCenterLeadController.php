<?php

namespace App\Http\Controllers;

use App\Models\CallCenterContact;
use App\Models\ContactCenterLead;
use App\Models\ContactCenterLeadEvent;
use App\Models\ContactCenterLeadItem;
use App\Models\Item;
use App\Models\ItemReservation;
use App\Models\Store;
use App\Services\ContactCenter\ItemReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Заявки и черновики контакт-центра. */
class ContactCenterLeadController extends Controller
{
    public function index(Request $request): View
    {
        $query = ContactCenterLead::query()
            ->with(['client', 'targetStore', 'assignee', 'createdByUser'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->whereNotIn('status', [
                ContactCenterLead::STATUS_CONVERTED,
                ContactCenterLead::STATUS_CLOSED_LOST,
                ContactCenterLead::STATUS_SPAM,
            ]);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('channel')) {
            $query->where('source_channel', $request->string('channel'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('lead_number', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('contact_phone', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $leads = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('contact-center.leads.index', [
            'leads' => $leads,
            'statuses' => ContactCenterLead::STATUSES,
            'types' => ContactCenterLead::TYPES,
            'channels' => ContactCenterLead::CHANNELS,
        ]);
    }

    public function create(Request $request): View
    {
        $presetContact = null;
        if ($request->filled('call_center_contact_id')) {
            $presetContact = CallCenterContact::query()->find($request->integer('call_center_contact_id'));
        }

        $presetItem = null;
        if ($request->filled('item_id')) {
            $presetItem = Item::query()->with('store')->find($request->integer('item_id'));
        }

        return view('contact-center.leads.create', [
            'stores' => Store::query()->where('is_active', true)->orderBy('name')->get(),
            'types' => ContactCenterLead::TYPES,
            'channels' => ContactCenterLead::CHANNELS,
            'presetContact' => $presetContact,
            'presetItem' => $presetItem,
            'presetType' => $request->string('type')->toString() ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = (string) $request->input('type', '');

        $rules = [
            'type' => ['required', Rule::in(array_keys(ContactCenterLead::TYPES))],
            'source_channel' => ['required', Rule::in(array_keys(ContactCenterLead::CHANNELS))],
            'client_id' => ['nullable', 'exists:clients,id'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'store_id_target' => ['nullable', 'exists:stores,id'],
            'item_id' => ['nullable', 'exists:items,id'],
            'call_center_contact_id' => ['nullable', 'exists:call_center_contacts,id'],
            'preferred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];

        // Для "заявки на продажу" позиции не нужны — выбираем конкретный товар.
        if ($type !== ContactCenterLead::TYPE_SALE_REQUEST) {
            $rules['items'] = ['nullable', 'array'];
            $rules['items.*.title'] = ['required_with:items', 'string', 'max:255'];
            $rules['items.*.description'] = ['nullable', 'string'];
            $rules['items.*.expected_price'] = ['nullable', 'numeric', 'min:0'];
            $rules['items.*.appraised_from'] = ['nullable', 'numeric', 'min:0'];
            $rules['items.*.appraised_to'] = ['nullable', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);

        $lead = DB::transaction(function () use ($data) {
            $lead = ContactCenterLead::create([
                'lead_number' => ContactCenterLead::generateLeadNumber(),
                'type' => $data['type'],
                'status' => ContactCenterLead::STATUS_NEW,
                'source_channel' => $data['source_channel'],
                'client_id' => $data['client_id'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'store_id_target' => $data['store_id_target'] ?? null,
                'item_id' => $data['item_id'] ?? null,
                'call_center_contact_id' => $data['call_center_contact_id'] ?? null,
                'preferred_at' => $data['preferred_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] ?? [] as $i => $row) {
                if (empty(trim((string) ($row['title'] ?? '')))) {
                    continue;
                }
                ContactCenterLeadItem::create([
                    'lead_id' => $lead->id,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'expected_price' => $row['expected_price'] ?? null,
                    'appraised_from' => $row['appraised_from'] ?? null,
                    'appraised_to' => $row['appraised_to'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            ContactCenterLeadEvent::create([
                'lead_id' => $lead->id,
                'channel' => $data['source_channel'],
                'event_type' => ContactCenterLeadEvent::EVENT_CREATED,
                'message' => 'Заявка создана',
                'created_by' => Auth::id(),
            ]);

            return $lead;
        });

        return redirect()
            ->route('contact-center.leads.show', $lead)
            ->with('success', 'Заявка '.$lead->lead_number.' создана.');
    }

    public function show(ContactCenterLead $lead): View
    {
        $lead->load([
            'client',
            'targetStore',
            'assignee',
            'item.store',
            'callCenterContact',
            'createdByUser',
            'items',
            'events.createdByUser',
            'activeReservation',
            'reservations',
        ]);

        return view('contact-center.leads.show', [
            'lead' => $lead,
            'stores' => Store::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ContactCenterLead::STATUSES,
            'lostReasons' => ContactCenterLead::LOST_REASONS,
            'reservationDays' => range(ItemReservation::MIN_DAYS, ItemReservation::MAX_DAYS),
        ]);
    }

    public function update(Request $request, ContactCenterLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ContactCenterLead::STATUSES))],
            'store_id_target' => ['nullable', 'exists:stores,id'],
            'preferred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lost_reason' => ['nullable', Rule::in(array_keys(ContactCenterLead::LOST_REASONS))],
        ]);

        $changes = [];

        if (array_key_exists('status', $data) && $data['status'] && $data['status'] !== $lead->status) {
            $changes['status'] = ['from' => $lead->status, 'to' => $data['status']];
            $lead->status = $data['status'];
        }

        if (array_key_exists('store_id_target', $data)) {
            if ((int) ($lead->store_id_target ?? 0) !== (int) ($data['store_id_target'] ?? 0)) {
                $changes['store_id_target'] = $data['store_id_target'];
                $lead->store_id_target = $data['store_id_target'];
            }
        }

        if (array_key_exists('preferred_at', $data)) {
            $lead->preferred_at = $data['preferred_at'];
        }

        if (array_key_exists('notes', $data)) {
            $lead->notes = $data['notes'];
        }

        if (array_key_exists('lost_reason', $data)) {
            $lead->lost_reason = $data['lost_reason'];
        }

        $lead->save();

        if (isset($changes['status'])) {
            ContactCenterLeadEvent::create([
                'lead_id' => $lead->id,
                'event_type' => ContactCenterLeadEvent::EVENT_STATUS,
                'message' => 'Статус: '.($lead->statusLabel()),
                'payload' => $changes['status'],
                'created_by' => Auth::id(),
            ]);
        }

        if (isset($changes['store_id_target'])) {
            $storeName = $lead->targetStore?->name ?? '—';
            ContactCenterLeadEvent::create([
                'lead_id' => $lead->id,
                'event_type' => ContactCenterLeadEvent::EVENT_ASSIGNMENT,
                'message' => 'Точка: '.$storeName,
                'payload' => ['store_id' => $lead->store_id_target],
                'created_by' => Auth::id(),
            ]);

            if ($lead->status === ContactCenterLead::STATUS_IN_WORK) {
                $lead->update(['status' => ContactCenterLead::STATUS_ASSIGNED]);
            }
        }

        return back()->with('success', 'Заявка обновлена.');
    }

    public function addNote(Request $request, ContactCenterLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ContactCenterLeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => ContactCenterLeadEvent::EVENT_NOTE,
            'message' => $data['message'],
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Заметка добавлена.');
    }

    public function assignStore(Request $request, ContactCenterLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'store_id_target' => ['required', 'exists:stores,id'],
        ]);

        $lead->store_id_target = $data['store_id_target'];
        $lead->status = ContactCenterLead::STATUS_ASSIGNED;
        $lead->save();

        $storeName = $lead->targetStore?->name ?? '';
        ContactCenterLeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => ContactCenterLeadEvent::EVENT_ASSIGNMENT,
            'message' => 'Передана в точку: '.$storeName,
            'payload' => ['store_id' => $lead->store_id_target],
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Заявка передана в точку.');
    }

    public function reserve(Request $request, ContactCenterLead $lead, ItemReservationService $service): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:'.ItemReservation::MIN_DAYS, 'max:'.ItemReservation::MAX_DAYS],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $reservation = $service->createForLead($lead, (int) $data['days'], $data['notes'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Товар забронирован до '.$reservation->reserved_until->format('d.m.Y').'.');
    }

    public function cancelReservation(ContactCenterLead $lead, ItemReservationService $service): RedirectResponse
    {
        $reservation = $lead->activeReservation;
        if (! $reservation) {
            return back()->with('error', 'Активной брони нет.');
        }

        try {
            $service->cancel($reservation, 'Отменено оператором');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Бронь отменена.');
    }

    /** Поиск обращений (звонки/Telegram) для привязки заявки. */
    public function searchContacts(Request $request): JsonResponse
    {
        $channel = trim((string) $request->get('channel', ''));
        $q = trim((string) $request->get('q', ''));

        if (! in_array($channel, ['phone', 'telegram', 'avito'], true) || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Avito-мессенджер сохраняется в call_center_contacts через AvitoInboxService.

        $storeIds = Auth::user()->allowedStoreIds();

        $query = CallCenterContact::query()
            ->with('client:id,full_name,phone')
            ->where('channel', $channel)
            ->where(function ($sub) use ($q) {
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
                $sub->orWhere('contact_phone', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhere('external_id', 'like', "%{$q}%");
            })
            ->orderByDesc('contact_date')
            ->limit(20);

        // Для звонков — ограничим видимостью по магазинам (как в колл-центре).
        $query->where(function ($sub) use ($storeIds) {
            $sub->whereNull('store_id')->orWhereIn('store_id', $storeIds);
        });

        $rows = $query->get(['id', 'client_id', 'channel', 'contact_date', 'contact_phone', 'contact_name', 'notes', 'external_id']);

        return response()->json($rows->map(function (CallCenterContact $c) {
            $dt = $c->contact_date
                ? (is_string($c->contact_date) ? \Carbon\Carbon::parse($c->contact_date) : $c->contact_date)
                : null;
            $label = trim('#'.$c->id.' · '.$c->channel_label.' · '.($dt ? $dt->format('d.m.Y H:i') : ''));
            $who = $c->client?->full_name ?: $c->contact_name ?: '—';
            $phone = $c->client?->phone ?: $c->contact_phone ?: '';
            $preview = trim((string) ($c->notes ?? ''));
            if (mb_strlen($preview) > 80) {
                $preview = mb_substr($preview, 0, 80).'…';
            }

            $suffix = trim($who.($phone ? ' · '.$phone : '').($preview ? ' · '.$preview : ''));

            return [
                'id' => $c->id,
                'label' => trim($label.($suffix ? ' · '.$suffix : '')),
                'url' => route('call-center.show', $c),
                'contact_name' => $c->contact_name,
                'contact_phone' => $c->contact_phone,
                'client' => $c->client ? [
                    'id' => $c->client->id,
                    'full_name' => $c->client->full_name,
                    'phone' => $c->client->phone,
                ] : null,
            ];
        }));
    }

    /** Последние обращения по каналу (для модального выбора). */
    public function recentContacts(Request $request): JsonResponse
    {
        $channel = trim((string) $request->get('channel', ''));
        $limit = (int) $request->get('limit', 30);
        $offset = (int) $request->get('offset', 0);

        if (! in_array($channel, ['phone', 'telegram', 'avito'], true)) {
            return response()->json(['ok' => false, 'items' => []], 422);
        }
        $limit = max(5, min(100, $limit));
        $offset = max(0, $offset);

        $storeIds = Auth::user()->allowedStoreIds();

        $query = CallCenterContact::query()
            ->with('client:id,full_name,phone')
            ->where('channel', $channel)
            ->where(function ($sub) use ($storeIds) {
                $sub->whereNull('store_id')->orWhereIn('store_id', $storeIds);
            })
            ->orderByDesc('contact_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->offset($offset);

        $rows = $query->get(['id', 'client_id', 'channel', 'contact_date', 'contact_phone', 'contact_name', 'notes', 'external_id']);

        $items = $rows->map(function (CallCenterContact $c) {
            $dt = $c->contact_date
                ? (is_string($c->contact_date) ? \Carbon\Carbon::parse($c->contact_date) : $c->contact_date)
                : null;
            $label = trim('#'.$c->id.' · '.$c->channel_label.' · '.($dt ? $dt->format('d.m.Y H:i') : ''));
            $who = $c->client?->full_name ?: $c->contact_name ?: '—';
            $phone = $c->client?->phone ?: $c->contact_phone ?: '';
            $preview = trim((string) ($c->notes ?? ''));
            if (mb_strlen($preview) > 80) {
                $preview = mb_substr($preview, 0, 80).'…';
            }
            $suffix = trim($who.($phone ? ' · '.$phone : '').($preview ? ' · '.$preview : ''));

            return [
                'id' => $c->id,
                'label' => trim($label.($suffix ? ' · '.$suffix : '')),
                'url' => route('call-center.show', $c),
                'contact_name' => $c->contact_name,
                'contact_phone' => $c->contact_phone,
                'client' => $c->client ? [
                    'id' => $c->client->id,
                    'full_name' => $c->client->full_name,
                    'phone' => $c->client->phone,
                ] : null,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'items' => $items,
            'next_offset' => $offset + $items->count(),
            'has_more' => $items->count() >= $limit,
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $items = Item::query()
            ->with('store:id,name')
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('lmb_ref', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['id', 'name', 'barcode', 'lmb_ref', 'current_price', 'store_id']);

        return response()->json($items->map(fn (Item $item) => [
            'id' => $item->id,
            'label' => trim(($item->lmb_ref ?: $item->barcode ?: '#'.$item->id).' — '.$item->name),
            'price' => $item->current_price,
            'store' => $item->store?->name,
        ]));
    }
}
