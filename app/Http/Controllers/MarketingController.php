<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TrafficSource;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Маркетинг: источники трафика, воронка, эффективность каналов. */
class MarketingController extends Controller
{
    public function index(Request $request): View
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
        ]);
    }
}
