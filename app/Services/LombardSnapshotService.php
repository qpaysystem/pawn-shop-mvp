<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PawnContract;

/** Краткий снимок данных ломбарда для Q&A агентов. */
class LombardSnapshotService
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $activePawns = PawnContract::query()->where('is_redeemed', false)->count();
        $clients = Client::query()->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'clients_total' => $clients,
            'active_pawn_contracts' => $activePawns,
            'lombard_name' => config('services.lombard.name', 'Ломбард'),
            'lombard_phone' => config('services.lombard.phone', ''),
        ];
    }
}
