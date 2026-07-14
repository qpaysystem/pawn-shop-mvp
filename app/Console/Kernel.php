<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('lmb:expire-item-reservations')
            ->hourly()
            ->withoutOverlapping(5)
            ->runInBackground();

        $schedule->command('avito:sync-inbox')
            ->everyFifteenMinutes()
            ->withoutOverlapping(12)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/avito-sync-inbox.log'));

        if (! (bool) config('services.mts_vpbx.pipeline_enabled', true)) {
            return;
        }

        $schedule->command('mts:portal-pipeline')
            ->everyFiveMinutes()
            ->withoutOverlapping(8)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/mts-portal-pipeline.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
