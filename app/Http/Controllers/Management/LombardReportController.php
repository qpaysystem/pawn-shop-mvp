<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Lombard\LombardReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Операционные отчёты ломбарда в разделе «Управление → Отчёты». */
class LombardReportController extends Controller
{
    public function __construct(
        private LombardReportService $reports,
    ) {}

    public function pawnsRedemptions(Request $request): View
    {
        $this->ensureAccess();
        [$from, $to, $storeId, $stores] = $this->filterContext($request);

        $data = $this->reports->pawnsAndRedemptions(
            auth()->user()->allowedStoreIds(),
            $storeId,
            $from,
            $to,
        );

        return view('management.reports.lombard.pawns', [
            'pageTitle' => 'Залоги и выкупы',
            'data' => $data,
            'stores' => $stores,
            'storeId' => $storeId,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ]);
    }

    public function pawnProfit(Request $request): View
    {
        $this->ensureAccess();
        [$from, $to, $storeId, $stores] = $this->filterContext($request);

        $data = $this->reports->pawnProfit(
            auth()->user()->allowedStoreIds(),
            $storeId,
            $from,
            $to,
        );

        return view('management.reports.lombard.pawn-profit', [
            'pageTitle' => 'Прибыль с залогов',
            'data' => $data,
            'stores' => $stores,
            'storeId' => $storeId,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ]);
    }

    public function salesProfit(Request $request): View
    {
        $this->ensureAccess();
        [$from, $to, $storeId, $stores] = $this->filterContext($request);

        $data = $this->reports->salesProfit(
            auth()->user()->allowedStoreIds(),
            $storeId,
            $from,
            $to,
        );

        return view('management.reports.lombard.sales-profit', [
            'pageTitle' => 'Прибыль по продажам',
            'data' => $data,
            'stores' => $stores,
            'storeId' => $storeId,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ]);
    }

    public function grossProfit(Request $request): View
    {
        $this->ensureAccess();
        [$from, $to, $storeId, $stores] = $this->filterContext($request);

        $data = $this->reports->grossProfit(
            auth()->user()->allowedStoreIds(),
            $storeId,
            $from,
            $to,
        );

        return view('management.reports.lombard.gross-profit', [
            'pageTitle' => 'Валовая прибыль',
            'data' => $data,
            'stores' => $stores,
            'storeId' => $storeId,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ]);
    }

    public function inventorySummary(Request $request): View
    {
        $this->ensureAccess();
        [$from, $to, $storeId, $stores] = $this->inventoryFilterContext($request);

        $stockKind = (string) $request->query('stock_kind', 'all');
        if (! array_key_exists($stockKind, $this->reports->stockKindOptions())) {
            $stockKind = 'all';
        }

        $itemKind = (string) $request->query('item_kind', 'all');
        if (! array_key_exists($itemKind, $this->reports->itemKindOptions())) {
            $itemKind = 'all';
        }

        $stockStatus = (string) $request->query('stock_status', 'in_stock');
        if (! array_key_exists($stockStatus, $this->reports->stockStatusOptions())) {
            $stockStatus = 'in_stock';
        }

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;

        $dateFrom = $request->filled('date_from') ? $from : null;
        $dateTo = $request->filled('date_to') ? $to : null;

        $data = $this->reports->inventoryRegister(
            auth()->user()->allowedStoreIds(),
            $storeId,
            $dateFrom,
            $dateTo,
            $stockKind,
            $itemKind,
            $categoryId,
            $stockStatus,
        );

        return view('management.reports.lombard.inventory', [
            'pageTitle' => 'Инвентаризация',
            'data' => $data,
            'stores' => $stores,
            'storeId' => $storeId,
            'dateFrom' => $request->filled('date_from') ? $from->format('Y-m-d') : '',
            'dateTo' => $request->filled('date_to') ? $to->format('Y-m-d') : '',
            'stockKind' => $stockKind,
            'itemKind' => $itemKind,
            'stockStatus' => $stockStatus,
            'categoryId' => $categoryId,
            'categories' => $this->reports->categoriesForFilter(),
            'stockKindOptions' => $this->reports->stockKindOptions(),
            'itemKindOptions' => $this->reports->itemKindOptions(),
            'stockStatusOptions' => $this->reports->stockStatusOptions(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?int, 3: \Illuminate\Support\Collection} */
    private function inventoryFilterContext(Request $request): array
    {
        $storeIds = auth()->user()->allowedStoreIds();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : $this->reports->defaultDateFrom();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : $this->reports->defaultDateTo();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $storeId = $request->filled('store_id') ? (int) $request->store_id : null;
        if ($storeId && ! in_array($storeId, $storeIds, true)) {
            $storeId = null;
        }

        return [$from, $to, $storeId, $this->reports->storesForFilter($storeIds)];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?int, 3: \Illuminate\Support\Collection} */
    private function filterContext(Request $request): array
    {
        $storeIds = auth()->user()->allowedStoreIds();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : $this->reports->defaultDateFrom();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : $this->reports->defaultDateTo();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $storeId = $request->filled('store_id') ? (int) $request->store_id : null;
        if ($storeId && ! in_array($storeId, $storeIds, true)) {
            $storeId = null;
        }

        return [$from, $to, $storeId, $this->reports->storesForFilter($storeIds)];
    }

    private function ensureAccess(): void
    {
        if (! auth()->user()->hasFullStoreAccess()) {
            abort(403);
        }
    }
}
