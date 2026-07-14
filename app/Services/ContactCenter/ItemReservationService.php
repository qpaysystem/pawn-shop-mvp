<?php

namespace App\Services\ContactCenter;

use App\Models\ContactCenterLead;
use App\Models\ContactCenterLeadEvent;
use App\Models\Item;
use App\Models\ItemReservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemReservationService
{
    public function createForLead(ContactCenterLead $lead, int $days, ?string $notes = null): ItemReservation
    {
        if (! $lead->item_id) {
            throw ValidationException::withMessages([
                'item_id' => 'Для брони нужен товар в заявке.',
            ]);
        }

        $days = max(ItemReservation::MIN_DAYS, min(ItemReservation::MAX_DAYS, $days));

        if (ItemReservation::query()
            ->where('item_id', $lead->item_id)
            ->where('status', ItemReservation::STATUS_ACTIVE)
            ->where('reserved_until', '>', now())
            ->exists()) {
            throw ValidationException::withMessages([
                'item_id' => 'Товар уже забронирован.',
            ]);
        }

        return DB::transaction(function () use ($lead, $days, $notes) {
            $reservation = ItemReservation::create([
                'item_id' => $lead->item_id,
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'contact_name' => $lead->contact_name,
                'contact_phone' => $lead->contact_phone,
                'status' => ItemReservation::STATUS_ACTIVE,
                'reserved_until' => now()->addDays($days)->endOfDay(),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $lead->update(['status' => ContactCenterLead::STATUS_RESERVED]);

            ContactCenterLeadEvent::create([
                'lead_id' => $lead->id,
                'event_type' => ContactCenterLeadEvent::EVENT_STATUS,
                'message' => 'Товар забронирован до '.$reservation->reserved_until->format('d.m.Y'),
                'payload' => ['reservation_id' => $reservation->id, 'days' => $days],
                'created_by' => Auth::id(),
            ]);

            return $reservation;
        });
    }

    public function cancel(ItemReservation $reservation, ?string $reason = null): void
    {
        if ($reservation->status !== ItemReservation::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Бронь уже не активна.',
            ]);
        }

        DB::transaction(function () use ($reservation, $reason) {
            $reservation->update([
                'status' => ItemReservation::STATUS_CANCELLED,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'notes' => trim(($reservation->notes ?? '').($reason ? "\nОтмена: {$reason}" : '')),
            ]);

            if ($reservation->lead_id) {
                $lead = $reservation->lead;
                if ($lead && $lead->status === ContactCenterLead::STATUS_RESERVED) {
                    $lead->update(['status' => ContactCenterLead::STATUS_IN_WORK]);
                }

                ContactCenterLeadEvent::create([
                    'lead_id' => $reservation->lead_id,
                    'event_type' => ContactCenterLeadEvent::EVENT_NOTE,
                    'message' => 'Бронь отменена'.($reason ? ": {$reason}" : ''),
                    'created_by' => Auth::id(),
                ]);
            }
        });
    }

    public function expireDue(): int
    {
        $expired = ItemReservation::query()
            ->where('status', ItemReservation::STATUS_ACTIVE)
            ->where('reserved_until', '<=', now())
            ->get();

        foreach ($expired as $reservation) {
            DB::transaction(function () use ($reservation) {
                $reservation->update(['status' => ItemReservation::STATUS_EXPIRED]);

                if ($reservation->lead_id) {
                    $lead = $reservation->lead;
                    if ($lead && $lead->status === ContactCenterLead::STATUS_RESERVED) {
                        $lead->update(['status' => ContactCenterLead::STATUS_WAITING_CLIENT]);
                    }

                    ContactCenterLeadEvent::create([
                        'lead_id' => $reservation->lead_id,
                        'event_type' => ContactCenterLeadEvent::EVENT_NOTE,
                        'message' => 'Бронь истекла '.$reservation->reserved_until->format('d.m.Y H:i'),
                    ]);
                }
            });
        }

        return $expired->count();
    }

    public function activeForItem(Item $item): ?ItemReservation
    {
        return ItemReservation::query()
            ->with(['client', 'lead', 'createdByUser'])
            ->where('item_id', $item->id)
            ->where('status', ItemReservation::STATUS_ACTIVE)
            ->where('reserved_until', '>', now())
            ->orderByDesc('id')
            ->first();
    }
}
