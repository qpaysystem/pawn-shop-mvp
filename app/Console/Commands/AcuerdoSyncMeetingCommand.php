<?php

namespace App\Console\Commands;

use App\Services\Meetings\MeetingReportSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AcuerdoSyncMeetingCommand extends Command
{
    protected $signature = 'acuerdo:sync-meeting {--force : Переразобрать, даже если уже в журнале}';

    protected $description = 'Загрузить последнее видеособрание с conf.nnfm.pro, транскрибировать и сохранить отчёт';

    public function handle(MeetingReportSyncService $sync): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $this->info('Загрузка последнего собрания…');

            $result = $sync->syncLatest(force: (bool) $this->option('force'));

            if (($result['skipped'] ?? '') === 'already_processed') {
                $report = $result['report'];
                $this->warn('Уже обработано: '.$report?->title);

                return self::SUCCESS;
            }

            if (! ($result['ok'] ?? false)) {
                $this->error($result['error'] ?? 'Ошибка обработки');

                return self::FAILURE;
            }

            $report = $result['report'];
            $this->info('Готово: '.$report->title.' (id='.$report->id.')');

            return self::SUCCESS;
        } finally {
            Cache::forget('meeting_sync_running');
        }
    }
}
