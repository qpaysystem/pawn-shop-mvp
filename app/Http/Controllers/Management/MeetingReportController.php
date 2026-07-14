<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\MeetingReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

/** Журнал отчётов по видеособраниям. */
class MeetingReportController extends Controller
{
    public function index(): View
    {
        $this->ensureAccess();

        $reports = MeetingReport::query()
            ->orderByDesc('meeting_at')
            ->paginate(20);

        return view('management.reports.meetings.index', [
            'reports' => $reports,
            'syncRunning' => $this->isSyncRunning(),
        ]);
    }

    public function show(MeetingReport $meetingReport): View
    {
        $this->ensureAccess();

        return view('management.reports.meetings.show', [
            'report' => $meetingReport,
        ]);
    }

    public function syncLatest(): RedirectResponse
    {
        $this->ensureAccess();

        if ($this->isSyncRunning()) {
            return redirect()
                ->route('management.reports.meetings.index')
                ->with('info', 'Обработка собрания уже запущена. Обновите страницу через несколько минут.');
        }

        Cache::put('meeting_sync_running', true, now()->addHours(2));

        $logFile = storage_path('logs/meeting-sync.log');
        $php = escapeshellarg(PHP_BINARY ?? 'php');
        $artisan = escapeshellarg(base_path('artisan'));
        $log = escapeshellarg($logFile);
        $shell = "{$php} {$artisan} acuerdo:sync-meeting --force >> {$log} 2>&1";

        try {
            Process::path(base_path())->start(['sh', '-c', $shell]);
        } catch (\Throwable $e) {
            Cache::forget('meeting_sync_running');
            Log::error('meeting sync start failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('management.reports.meetings.index')
                ->with('error', 'Не удалось запустить обработку: '.$e->getMessage());
        }

        return redirect()
            ->route('management.reports.meetings.index')
            ->with('success', 'Запущена обработка последнего собрания. Страница обновится через 2–5 минут — нажмите F5.');
    }

    private function isSyncRunning(): bool
    {
        if (Cache::get('meeting_sync_running')) {
            return true;
        }

        return MeetingReport::query()
            ->where('status', MeetingReport::STATUS_PROCESSING)
            ->where('updated_at', '>=', now()->subHours(2))
            ->exists();
    }

    private function ensureAccess(): void
    {
        if (! auth()->user()->hasFullStoreAccess()) {
            abort(403);
        }
    }
}
