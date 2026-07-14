<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Lombard\LombardReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile v1: сводные отчёты по прибыли. */
class ReportController extends Controller
{
    public function __construct(
        private LombardReportService $reports,
    ) {}

    public function profit(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasFullStoreAccess()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $storeIds = $user->allowedStoreIds();
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

        $pawn = $this->reports->pawnProfit($storeIds, $storeId, $from, $to, 0);
        $sales = $this->reports->salesProfit($storeIds, $storeId, $from, $to, 0);

        return response()->json([
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'pawn' => [
                'totals' => $pawn['totals'],
                'by_store' => $pawn['by_store'],
            ],
            'sales' => [
                'totals' => $sales['totals'],
                'by_store' => $sales['by_store'],
            ],
        ]);
    }
}
