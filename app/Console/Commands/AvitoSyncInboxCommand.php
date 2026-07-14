<?php

namespace App\Console\Commands;

use App\Services\Avito\AvitoInboxSyncService;
use Illuminate\Console\Command;

/** Подгрузка чатов и сообщений Avito по активным объявлениям. */
class AvitoSyncInboxCommand extends Command
{
    protected $signature = 'avito:sync-inbox {--branch= : slug филиала (по умолчанию — все настроенные)}';

    protected $description = 'Загрузить в портал все обращения Avito по действующим объявлениям';

    public function handle(AvitoInboxSyncService $sync): int
    {
        $branch = trim((string) $this->option('branch'));
        $branch = $branch !== '' ? $branch : null;

        $this->info('Синхронизация Avito inbox...');

        $result = $sync->syncActiveListingInquiries($branch);
        if (! ($result['ok'] ?? false)) {
            $this->error($result['error'] ?? 'Ошибка синхронизации');

            return self::FAILURE;
        }

        $totals = $result['totals'] ?? [];
        $this->info(sprintf(
            'Активных объявлений: %d · чатов по ним: %d · сообщений загружено: %d',
            (int) ($totals['active_listings'] ?? 0),
            (int) ($totals['chats_synced'] ?? 0),
            (int) ($totals['messages_ingested'] ?? 0),
        ));

        foreach ($result['branches'] ?? [] as $branchResult) {
            if (! ($branchResult['ok'] ?? false)) {
                $this->warn(($branchResult['branch'] ?? '?').': '.($branchResult['error'] ?? 'ошибка'));

                continue;
            }
            $this->line(sprintf(
                '  %s: чатов %d/%d, сообщений %d (новых входящих +%d)',
                $branchResult['branch'] ?? '?',
                (int) ($branchResult['chats_matched'] ?? 0),
                (int) ($branchResult['chats_total'] ?? 0),
                (int) ($branchResult['messages_ingested'] ?? 0),
                (int) ($branchResult['incoming_new'] ?? 0),
            ));
        }

        return self::SUCCESS;
    }
}
