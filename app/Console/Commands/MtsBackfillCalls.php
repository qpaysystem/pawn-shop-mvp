<?php

namespace App\Console\Commands;

use App\Services\CallCenterMtsAutoEnrichmentService;
use App\Services\CallCenterMtsImportService;
use App\Services\MtsVpbxService;
use Illuminate\Console\Command;

/**
 * Полный backfill: расшифровать все звонки MTS за период и отправить тексты в портал ИИ.
 * Запускает порциями до опустошения очереди.
 */
class MtsBackfillCalls extends Command
{
    protected $signature = 'mts:backfill
                            {--days=90 : За сколько дней обрабатывать звонки}
                            {--batch=50 : Размер порции за итерацию}
                            {--enrich-only : Только расшифровка, без отдельного прохода по порталу}
                            {--portal-only : Только отправка готовых расшифровок в портал}';

    protected $description = 'MTS: расшифровать все звонки за период и отправить в портал ИИ';

    public function handle(
        CallCenterMtsImportService $import,
        CallCenterMtsAutoEnrichmentService $enrich,
    ): int {
        if (! app(MtsVpbxService::class)->isConfigured()) {
            $this->error('MTS не настроен.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $batch = max(1, (int) $this->option('batch'));
        $portalOnly = (bool) $this->option('portal-only');
        $enrichOnly = (bool) $this->option('enrich-only');

        if (! $portalOnly) {
            $this->info("Расшифровка звонков MTS за {$days} дн., порция {$batch}…");
            $totalEnrich = ['processed' => 0, 'recordings' => 0, 'transcripts' => 0, 'portal_pushed' => 0, 'errors' => 0];
            do {
                $pending = $import->contactsPendingEnrichment($days, $batch);
                if ($pending->isEmpty()) {
                    break;
                }
                try {
                    $stats = $enrich->enrichContacts($pending, $batch);
                } catch (\Throwable $e) {
                    $this->warn('  порция: ошибка — '.$e->getMessage().' (продолжаем)');
                    $stats = ['processed' => 0, 'recordings' => 0, 'transcripts' => 0, 'portal_pushed' => 0, 'errors' => 1];
                    sleep(5);
                }
                foreach ($stats as $k => $v) {
                    $totalEnrich[$k] += $v;
                }
                $this->line(sprintf(
                    '  порция: +%d расшифровок, +%d в портал',
                    $stats['transcripts'],
                    $stats['portal_pushed'],
                ));
            } while ($pending->count() >= $batch);

            $this->info(sprintf(
                'Итого расшифровка: обработано %d, записей %d, текстов %d, в портал %d, ошибок %d',
                $totalEnrich['processed'],
                $totalEnrich['recordings'],
                $totalEnrich['transcripts'],
                $totalEnrich['portal_pushed'],
                $totalEnrich['errors'],
            ));
        }

        if ($enrichOnly) {
            return self::SUCCESS;
        }

        $this->info("Отправка расшифровок в портал ИИ за {$days} дн.…");
        $totalPortal = ['processed' => 0, 'portal_pushed' => 0, 'errors' => 0];
        do {
            $pendingPortal = $import->contactsPendingPortalPush($days, $batch);
            if ($pendingPortal->isEmpty()) {
                break;
            }
            $stats = $enrich->pushContactsToPortal($pendingPortal, $batch);
            foreach ($stats as $k => $v) {
                $totalPortal[$k] += $v;
            }
            $this->line(sprintf('  порция: +%d в портал', $stats['portal_pushed']));
        } while ($pendingPortal->count() >= $batch);

        $this->info(sprintf(
            'Итого портал: отправлено %d из %d (ошибок %d)',
            $totalPortal['portal_pushed'],
            $totalPortal['processed'],
            $totalPortal['errors'],
        ));

        return self::SUCCESS;
    }
}
