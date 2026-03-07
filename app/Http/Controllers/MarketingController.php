<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Marketing2GisStat;
use App\Models\TrafficSource;
use App\Services\TwoGisApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Маркетинг: источники трафика, воронка, эффективность каналов, данные 2ГИС. */
class MarketingController extends Controller
{
    public function index(Request $request, TwoGisApiService $twoGis): View
    {
        $sources = TrafficSource::orderBy('sort_order')->get();
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $clientsQuery = Client::query();
        if ($dateFrom) {
            $clientsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $clientsQuery->whereDate('created_at', '<=', $dateTo);
        }
        $clientsBase = clone $clientsQuery;

        // По источникам: количество клиентов с этим источником
        $bySource = [];
        foreach ($sources as $source) {
            $q = (clone $clientsBase)->where('traffic_source_id', $source->id);
            $total = $q->count();
            $deals = (clone $q)->where('funnel_stage', Client::FUNNEL_DEAL)->count();
            $withContracts = (clone $q)->whereHas('pawnContracts')
                ->orWhereHas('commissionContracts')
                ->orWhereHas('purchaseContracts')->count();
            $bySource[] = [
                'source' => $source,
                'total' => $total,
                'deals_stage' => $deals,
                'with_contracts' => $withContracts,
                'conversion' => $total > 0 ? round($with_contracts / $total * 100, 1) : 0,
            ];
        }

        // Без источника
        $noSource = (clone $clientsBase)->whereNull('traffic_source_id')->count();
        $noSourceDeals = (clone $clientsBase)->whereNull('traffic_source_id')
            ->where(function ($q) {
                $q->whereHas('pawnContracts')
                    ->orWhereHas('commissionContracts')
                    ->orWhereHas('purchaseContracts');
            })->count();

        // Воронка: по этапам (всего и по каждому источнику для фильтра)
        $funnelStages = [
            Client::FUNNEL_LEAD => Client::funnelStageLabels()[Client::FUNNEL_LEAD],
            Client::FUNNEL_CONTACT => Client::funnelStageLabels()[Client::FUNNEL_CONTACT],
            Client::FUNNEL_VISIT => Client::funnelStageLabels()[Client::FUNNEL_VISIT],
            Client::FUNNEL_DEAL => Client::funnelStageLabels()[Client::FUNNEL_DEAL],
        ];
        $funnelTotal = [];
        foreach (array_keys($funnelStages) as $stage) {
            $funnelTotal[$stage] = (clone $clientsBase)->where('funnel_stage', $stage)->count();
        }
        $clientsWithAnyContract = (clone $clientsBase)->where(function ($q) {
            $q->whereHas('pawnContracts')
                ->orWhereHas('commissionContracts')
                ->orWhereHas('purchaseContracts');
        })->count();

        // Данные из 2ГИС: карточка организации (кэш 1 ч) + статистика просмотров/звонков по дням
        $dgisBranch = $twoGis->getBranchInfo();
        $dgisStatsQuery = Marketing2GisStat::query();
        if ($dateFrom) {
            $dgisStatsQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $dgisStatsQuery->whereDate('date', '<=', $dateTo);
        }
        $dgisStats = $dgisStatsQuery->orderByDesc('date')->limit(365)->get();
        $dgisTotals = [
            'views' => $dgisStats->sum('views_count'),
            'calls' => $dgisStats->sum('calls_count'),
        ];

        return view('marketing.index', [
            'sources' => $sources,
            'bySource' => $bySource,
            'noSource' => $noSource,
            'noSourceDeals' => $noSourceDeals,
            'funnelStages' => $funnelStages,
            'funnelTotal' => $funnelTotal,
            'clientsWithAnyContract' => $clientsWithAnyContract,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dgisBranch' => $dgisBranch,
            'dgisStats' => $dgisStats,
            'dgisTotals' => $dgisTotals,
        ]);
    }

    /** Обновить данные из 2ГИС (сброс кэша и редирект). */
    public function refresh2Gis(TwoGisApiService $twoGis): RedirectResponse
    {
        $twoGis->clearCache();
        return redirect()->route('marketing.index')->with('success', 'Кэш 2ГИС сброшен. Данные подтянутся при открытии раздела.');
    }

    /** Сохранить запись статистики 2ГИС (просмотры/звонки за день). */
    public function store2GisStat(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'views_count' => 'nullable|integer|min:0',
            'calls_count' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:500',
        ]);
        Marketing2GisStat::updateOrCreate(
            ['date' => $validated['date']],
            [
                'views_count' => (int) ($validated['views_count'] ?? 0),
                'calls_count' => (int) ($validated['calls_count'] ?? 0),
                'comment' => $validated['comment'] ?? null,
            ]
        );
        return redirect()->route('marketing.index', $request->only(['date_from', 'date_to']) + ['dgis' => '1'])
            ->with('success', 'Запись по 2ГИС сохранена.')
            ->withFragment('dgis');
    }
}
