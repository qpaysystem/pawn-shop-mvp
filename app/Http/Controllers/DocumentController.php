<?php

namespace App\Http\Controllers;

use App\Models\CashDocument;
use App\Models\CommissionContract;
use App\Models\Expense;
use App\Models\PawnContract;
use App\Models\PayrollAccrual;
use App\Models\PurchaseContract;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Сводный список всех документов системы и проводки по ним. */
class DocumentController extends Controller
{
    /** Все документы: договоры залога, комиссии, скупки, кассовые, ФОТ, расходы. */
    public function index(Request $request): View
    {
        $storeIds = auth()->user()->allowedStoreIds();
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $typeFilter = $request->get('type');

        $rows = collect();

        if (! $typeFilter || $typeFilter === 'pawn_contract') {
            $pawn = PawnContract::with('store')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('loan_date', [$dateFrom, $dateTo])
                ->orderByDesc('loan_date')
                ->get();
            foreach ($pawn as $doc) {
                $rows->push((object) [
                    'document_type' => 'pawn_contract',
                    'type_label' => 'Договор залога',
                    'number' => $doc->contract_number,
                    'date' => $doc->loan_date,
                    'amount' => $doc->loan_amount,
                    'url' => route('pawn-contracts.show', $doc),
                ]);
            }
        }

        if (! $typeFilter || $typeFilter === 'commission_contract') {
            $comm = CommissionContract::with('store')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('expiry_date', [$dateFrom, $dateTo])
                ->orderByDesc('expiry_date')
                ->get();
            foreach ($comm as $doc) {
                $rows->push((object) [
                    'document_type' => 'commission_contract',
                    'type_label' => 'Договор комиссии',
                    'number' => $doc->contract_number,
                    'date' => $doc->expiry_date,
                    'amount' => $doc->seller_price ?? $doc->commission_amount,
                    'url' => route('commission-contracts.show', $doc),
                ]);
            }
        }

        if (! $typeFilter || $typeFilter === 'purchase_contract') {
            $purch = PurchaseContract::with('store')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('purchase_date', [$dateFrom, $dateTo])
                ->orderByDesc('purchase_date')
                ->get();
            foreach ($purch as $doc) {
                $rows->push((object) [
                    'document_type' => 'purchase_contract',
                    'type_label' => 'Договор скупки',
                    'number' => $doc->contract_number,
                    'date' => $doc->purchase_date,
                    'amount' => $doc->purchase_amount,
                    'url' => route('purchase-contracts.show', $doc),
                ]);
            }
        }

        if (! $typeFilter || $typeFilter === 'cash_document') {
            $cash = CashDocument::with('store')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('document_date', [$dateFrom, $dateTo])
                ->orderByDesc('document_date')
                ->get();
            foreach ($cash as $doc) {
                $rows->push((object) [
                    'document_type' => 'cash_document',
                    'type_label' => 'Кассовый документ',
                    'number' => $doc->document_number ?? '№' . $doc->id,
                    'date' => $doc->document_date,
                    'amount' => $doc->amount,
                    'url' => route('cash.index', ['date_from' => \Carbon\Carbon::parse($doc->document_date)->format('Y-m-d'), 'date_to' => \Carbon\Carbon::parse($doc->document_date)->format('Y-m-d')]),
                ]);
            }
        }

        if (! $typeFilter || $typeFilter === 'payroll_accrual') {
            $payroll = PayrollAccrual::whereBetween('accrual_date', [$dateFrom, $dateTo])
                ->orderByDesc('accrual_date')
                ->get();
            foreach ($payroll as $doc) {
                $rows->push((object) [
                    'document_type' => 'payroll_accrual',
                    'type_label' => 'Начисление ФОТ',
                    'number' => $doc->number ?? '№' . $doc->id,
                    'date' => $doc->accrual_date,
                    'amount' => $doc->total_amount,
                    'url' => route('payroll-accruals.show', $doc),
                ]);
            }
        }

        if (! $typeFilter || $typeFilter === 'expense') {
            $exp = Expense::with('store')
                ->whereIn('store_id', $storeIds)
                ->whereBetween('expense_date', [$dateFrom, $dateTo])
                ->orderByDesc('expense_date')
                ->get();
            foreach ($exp as $doc) {
                $rows->push((object) [
                    'document_type' => 'expense',
                    'type_label' => 'Расход',
                    'number' => $doc->number ?? '№' . $doc->id,
                    'date' => $doc->expense_date,
                    'amount' => $doc->amount,
                    'url' => route('expenses.show', $doc),
                ]);
            }
        }

        $rows = $rows->sortByDesc('date')->values();
        $perPage = 30;
        $page = (int) $request->get('page', 1);
        $total = $rows->count();
        $rows = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return view('documents.index', [
            'rows' => $rows,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'typeFilter' => $typeFilter,
            'paginator' => new \Illuminate\Pagination\LengthAwarePaginator($rows, $total, $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]),
        ]);
    }
}
