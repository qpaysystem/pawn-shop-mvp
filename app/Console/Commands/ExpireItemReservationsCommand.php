<?php

namespace App\Console\Commands;

use App\Services\ContactCenter\ItemReservationService;
use Illuminate\Console\Command;

/** Снятие просроченных броней товаров. */
class ExpireItemReservationsCommand extends Command
{
    protected $signature = 'lmb:expire-item-reservations';

    protected $description = 'Закрыть просроченные брони товаров контакт-центра';

    public function handle(ItemReservationService $service): int
    {
        $count = $service->expireDue();
        $this->info("Истекло броней: {$count}");

        return self::SUCCESS;
    }
}
