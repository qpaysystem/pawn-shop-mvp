<?php

namespace App\Console\Commands;

use App\Models\CallCenterContact;
use App\Models\Client;
use App\Services\MtsVpbxService;
use Illuminate\Console\Command;

class SyncMtsVpbxCalls extends Command
{
    protected $signature = 'mts:sync-calls
                            {--days=7 : За сколько дней загружать звонки}
                            {--dry-run : Не сохранять в БД, только показать что загрузится}';

    protected $description = 'Загрузить звонки из MTS VPBX (vpbx.mts.ru) в колл-центр';

    public function handle(): int
    {
        $service = app(MtsVpbxService::class);
        if (! $service->isConfigured()) {
            $this->error('MTS VPBX не настроен. Укажите MTS_VPBX_URL и MTS_VPBX_PASSWORD в .env');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $days = max(1, min(90, $days));
        $dateTo = now();
        $dateFrom = now()->subDays($days);

        $this->info("Загрузка звонков с {$dateFrom->format('Y-m-d')} по {$dateTo->format('Y-m-d')}...");

        $calls = $service->fetchCalls($dateFrom, $dateTo);

        if ($calls === []) {
            $this->warn('Звонков не получено. Проверьте URL и пароль API, а также формат ответа vpbx.mts.ru.');
            return self::SUCCESS;
        }

        $this->info('Получено записей: ' . count($calls));

        if ($this->option('dry-run')) {
            foreach (array_slice($calls, 0, 10) as $c) {
                $this->line("  {$c['contact_date']} | {$c['direction']} | {$c['contact_phone']} | {$c['external_id']}");
            }
            if (count($calls) > 10) {
                $this->line('  ... и ещё ' . (count($calls) - 10));
            }
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($calls as $call) {
            if (CallCenterContact::where('external_id', $call['external_id'])->exists()) {
                $skipped++;
                continue;
            }

            $clientId = null;
            if (! empty($call['contact_phone'])) {
                $phone = $call['contact_phone'];
                $normalized = preg_replace('/\D/', '', $phone);
                $client = Client::where('phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$normalized])
                    ->first();
                $clientId = $client?->id;
            }

            CallCenterContact::create([
                'external_id' => $call['external_id'],
                'client_id' => $clientId,
                'channel' => 'phone',
                'direction' => $call['direction'],
                'store_id' => null,
                'contact_date' => $call['contact_date'],
                'contact_phone' => $call['contact_phone'],
                'contact_name' => null,
                'notes' => $call['notes'],
                'outcome' => null,
                'created_by' => null,
            ]);
            $created++;
        }

        $this->info("Создано обращений: {$created}, пропущено (дубли): {$skipped}.");
        return self::SUCCESS;
    }
}
