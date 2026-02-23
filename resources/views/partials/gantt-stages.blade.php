@php
    $stages = $stages ?? collect();
    $allDates = collect();
    foreach ($stages as $s) {
        if ($s->planned_start_date) $allDates->push($s->planned_start_date);
        if ($s->planned_end_date) $allDates->push($s->planned_end_date);
        foreach ($s->works ?? [] as $w) {
            if ($w->work_start_date) $allDates->push($w->work_start_date);
        }
    }
    $minDate = $allDates->min();
    $maxDate = $allDates->max();
    if (!$minDate) $minDate = now()->startOfMonth();
    if (!$maxDate) $maxDate = $minDate->copy()->addMonths(2);
    if ($maxDate->lte($minDate)) $maxDate = $minDate->copy()->addMonth();
    $totalDays = $minDate->diffInDays($maxDate) + 1;
    $totalDays = max(1, $totalDays);
@endphp
<div class="gantt-stages-wrap">
    <div class="gantt-timeline-header d-flex border-bottom mb-2 pb-2 small text-muted align-items-center">
        <div class="gantt-label-col" style="width: 220px; min-width: 220px;">Этап / вид работ</div>
        <div class="gantt-chart-col flex-grow-1 text-center">{{ $minDate->format('d.m.Y') }} — {{ $maxDate->format('d.m.Y') }}</div>
    </div>
    <div class="gantt-rows">
        @foreach($stages as $stage)
        @php
            $start = $stage->planned_start_date ?? $stage->planned_end_date ?? $minDate;
            $end = $stage->planned_end_date ?? $stage->planned_start_date ?? $minDate;
            if ($end < $start) $end = $start;
            $startDay = $minDate->diffInDays($start);
            $spanDays = $start->diffInDays($end) + 1;
            $leftPct = ($startDay / $totalDays) * 100;
            $widthPct = max(2, ($spanDays / $totalDays) * 100);
        @endphp
        <div class="gantt-row border-bottom py-1 align-items-center" data-stage-id="{{ $stage->id }}">
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-link btn-sm p-0 me-1 gantt-expand-btn text-secondary" style="width: 24px; min-width: 24px;" aria-label="Раскрыть виды работ" title="Виды работ">
                    <i class="bi bi-chevron-right gantt-expand-icon"></i>
                </button>
                <div class="gantt-label-col flex-grow-1" style="max-width: 200px;">
                    <span class="fw-semibold">{{ Str::limit($stage->name, 35) }}</span>
                    @if($stage->planned_end_date)<br><span class="small text-muted">{{ $stage->planned_start_date?->format('d.m.Y') ?? '—' }} – {{ $stage->planned_end_date->format('d.m.Y') }}</span>@endif
                </div>
                <div class="gantt-chart-col flex-grow-1 position-relative" style="height: 28px;">
                    <div class="gantt-bar gantt-bar-stage rounded" style="left: {{ $leftPct }}%; width: {{ $widthPct }}%;" title="{{ $stage->planned_start_date?->format('d.m.Y') ?? '—' }} – {{ $stage->planned_end_date?->format('d.m.Y') ?? '—' }}"></div>
                </div>
            </div>
            <div class="gantt-works-detail d-none mt-1 ms-4" id="gantt-works-{{ $stage->id }}">
                @forelse($stage->works ?? [] as $work)
                @php
                    $wStart = $work->work_start_date ?? $minDate;
                    $wLeft = ($minDate->diffInDays($wStart) / $totalDays) * 100;
                    $wWidth = max(1, 100 / $totalDays);
                @endphp
                <div class="d-flex align-items-center py-1 small">
                    <div class="gantt-label-col" style="width: 200px; min-width: 200px;" title="{{ $work->works_name ?: $work->materials_name ?: '—' }}">{{ Str::limit($work->works_name ?: $work->materials_name ?: 'Вид работ', 30) ?: '—' }}</div>
                    <div class="gantt-chart-col flex-grow-1 position-relative" style="height: 20px;">
                        <div class="gantt-bar gantt-bar-work rounded" style="left: {{ $wLeft }}%; width: {{ $wWidth }}%;" title="{{ $work->work_start_date?->format('d.m.Y') ?? '—' }}"></div>
                    </div>
                </div>
                @empty
                <div class="small text-muted py-1">Нет видов работ</div>
                @endforelse
            </div>
        </div>
        @endforeach
        @if($stages->isEmpty())
        <div class="text-muted text-center py-4">Нет этапов для отображения на диаграмме.</div>
        @endif
    </div>
</div>
<style>
.gantt-stages-wrap { font-size: 0.9rem; }
.gantt-chart-col { min-height: 24px; }
.gantt-bar { position: absolute; top: 2px; height: calc(100% - 4px); min-width: 4px; }
.gantt-bar-stage { background: #0d6efd; opacity: 0.85; }
.gantt-bar-work { background: #6c757d; opacity: 0.8; }
.gantt-day-marker { position: absolute; font-size: 0.7rem; }
.gantt-expand-icon { transition: transform 0.2s; }
.gantt-row.expanded .gantt-expand-icon { transform: rotate(90deg); }
</style>
<script>
(function() {
    document.querySelectorAll('.gantt-stages-wrap .gantt-expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.gantt-row');
            var stageId = row && row.dataset.stageId;
            if (!stageId) return;
            var detail = document.getElementById('gantt-works-' + stageId);
            if (detail) {
                detail.classList.toggle('d-none');
                row.classList.toggle('expanded');
            }
        });
    });
})();
</script>
