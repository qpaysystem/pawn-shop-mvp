<?php

namespace App\Console\Commands;

use App\Services\CallCenterMtsAutoEnrichmentService;
use App\Services\CallCenterMtsImportService;
use App\Services\MtsVpbxService;
use Illuminate\Console\Command;

/**
 * Периодический пайплайн: звонки MTS → записи → транскрипт → agent-teams-portal.
 * Запускается планировщиком каждые 5 минут порциями до полного покрытия backlog.
 */
class MtsPortalPipeline extends Command
{
    protected $signature = 'mts:portal-pipeline
                            {--days= : Дней назад для загрузки звонков (по умолчанию из config)}
                            {--enrich-limit= : Макс. звонков на расшифровку за запуск}
                            {--portal-limit= : Макс. звонков на отправку в портал за запуск}';

    protected $description = 'MTS: загрузить новые звонки, расшифровать порцию, отправить в портал ИИ';

    public function handle(
        CallCenterMtsImportService $import,
        CallCenterMtsAutoEnrichmentService $enrich,
    ): int {
        if (! (bool) config('services.mts_vpbx.pipeline_enabled', true)) {
            $this->comment('Пайплайн отключён (MTS_PIPELINE_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! app(MtsVpbxService::class)->isConfigured()) {
            $this->warn('MTS не настроен — пропуск.');

            return self::SUCCESS;
        }

        $syncDays = (int) ($this->option('days') ?? config('services.mts_vpbx.pipeline_sync_days', 1));
        $enrichLimit = (int) ($this->option('enrich-limit') ?? config('services.mts_vpbx.pipeline_enrich_limit', 50));
        $portalLimit = (int) ($this->option('portal-limit') ?? config('services.mts_vpbx.pipeline_portal_push_limit', 50));

        $importStats = $import->importRecentCalls($syncDays);
        $this->line(sprintf(
            'Звонки: получено %d, новых %d, обновлено %d',
            $importStats['fetched'],
            $importStats['created'],
            $importStats['updated'],
        ));

        $backlogDays = (int) config('services.mts_vpbx.pipeline_backlog_days', 90);
        // Свежие без расшифровки первыми; безнадёжные/отложенные уже отфильтрованы в запросе.
        $toEnrich = $import->contactsPendingEnrichment($backlogDays, $enrichLimit);

        if ($toEnrich->isNotEmpty()) {
            $stats = $enrich->enrichContacts($toEnrich, $enrichLimit);
            $this->info(sprintf(
                'Обогащение: обработано %d, записей %d, расшифровок %d, в портал %d, ошибок %d',
                $stats['processed'],
                $stats['recordings'],
                $stats['transcripts'],
                $stats['portal_pushed'],
                $stats['errors'],
            ));
        } else {
            $this->info('Нет звонков для расшифровки в этой порции.');
        }

        $pendingPortal = $import->contactsPendingPortalPush($backlogDays, $portalLimit);
        if ($pendingPortal->isEmpty()) {
            $this->info('Все расшифровки за период уже в портале ИИ.');

            return self::SUCCESS;
        }

        $portalStats = $enrich->pushContactsToPortal($pendingPortal, $portalLimit);
        $this->info(sprintf(
            'Портал ИИ: отправлено %d из %d (ошибок %d)',
            $portalStats['portal_pushed'],
            $portalStats['processed'],
            $portalStats['errors'],
        ));

        return self::SUCCESS;
    }
}
