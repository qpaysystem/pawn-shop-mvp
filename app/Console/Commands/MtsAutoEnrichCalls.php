<?php

namespace App\Console\Commands;

use App\Models\CallCenterContact;
use App\Services\CallCenterMtsAutoEnrichmentService;
use Illuminate\Console\Command;

/** Загрузить записи MTS, расшифровать и отправить в agent-teams-portal. */
class MtsAutoEnrichCalls extends Command
{
    protected $signature = 'mts:auto-enrich
                            {--days=90 : За сколько дней искать звонки}
                            {--limit=100 : Максимум звонков за запуск (0 = без лимита)}
                            {--missing-transcript : Только без расшифровки}
                            {--portal-only : Только отправить готовые расшифровки в портал}';

    protected $description = 'MTS: записи → транскрипт → портал ИИ-агентов';

    public function handle(CallCenterMtsAutoEnrichmentService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $limitOpt = (int) $this->option('limit');
        $limit = $limitOpt === 0 ? PHP_INT_MAX : max(1, $limitOpt);

        if ($this->option('portal-only')) {
            $pending = app(\App\Services\CallCenterMtsImportService::class)
                ->contactsPendingPortalPush($days, $limit);
            if ($pending->isEmpty()) {
                $this->info('Нет расшифровок для отправки в портал.');

                return self::SUCCESS;
            }
            $stats = $service->pushContactsToPortal($pending, $limit);
            $this->info(sprintf(
                'В портал: отправлено %d из %d (ошибок %d)',
                $stats['portal_pushed'],
                $stats['processed'],
                $stats['errors'],
            ));

            return self::SUCCESS;
        }

        if ($this->option('missing-transcript')) {
            $pending = app(\App\Services\CallCenterMtsImportService::class)
                ->contactsPendingEnrichment($days, $limit === PHP_INT_MAX ? 10000 : $limit);
            $contacts = $pending;
        } else {
            $from = now()->subDays($days)->startOfDay();
            $contacts = CallCenterContact::query()
                ->where('channel', 'phone')
                ->where('external_id', 'like', 'mts_%')
                ->where('contact_date', '>=', $from)
                ->orderByDesc('contact_date')
                ->limit($limit === PHP_INT_MAX ? 10000 : $limit)
                ->get();
        }
        if ($contacts->isEmpty()) {
            $this->info('Нет звонков для обработки.');

            return self::SUCCESS;
        }

        $stats = $service->enrichContacts($contacts, $limit);
        $this->info(sprintf(
            'Обработано: %d, записей: %d, расшифровок: %d, в портал: %d, ошибок: %d',
            $stats['processed'],
            $stats['recordings'],
            $stats['transcripts'],
            $stats['portal_pushed'],
            $stats['errors'],
        ));

        return self::SUCCESS;
    }
}
