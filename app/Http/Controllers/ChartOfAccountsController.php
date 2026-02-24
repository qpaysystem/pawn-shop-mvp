<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** План счетов, карточки счетов, оборотно-сальдовая ведомость. */
class ChartOfAccountsController extends Controller
{
    public function index(): View
    {
        $accounts = Account::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        return view('chart-of-accounts.index', compact('accounts'));
    }

    /** Карточка счёта: движения по счёту за период. */
    public function show(Request $request, Account $account): View
    {
        $storeIds = auth()->user()->allowedStoreIds();
        $stores = Store::whereIn('id', $storeIds)->where('is_active', true)->orderBy('name')->get();
        $clients = Client::orderBy('full_name')->get();

        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $storeId = $request->get('store_id');
        $clientId = $request->get('client_id') ? (int) $request->get('client_id') : null;

        $query = LedgerEntry::with(['store', 'createdByUser', 'client'])
            ->where('account_id', $account->id)
            ->whereBetween('entry_date', [$dateFrom, $dateTo]);

        if ($storeId && in_array((int) $storeId, $storeIds, true)) {
            $query->where('store_id', $storeId);
        } else {
            $query->where(function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)->orWhereNull('store_id');
            });
        }
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $entries = $query->orderBy('entry_date')->orderBy('id')->paginate(50)->withQueryString();

        $totals = LedgerEntry::where('account_id', $account->id)
            ->whereBetween('entry_date', [$dateFrom, $dateTo]);
        if ($storeId && in_array((int) $storeId, $storeIds, true)) {
            $totals->where('store_id', $storeId);
        } else {
            $totals->where(function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)->orWhereNull('store_id');
            });
        }
        if ($clientId) {
            $totals->where('client_id', $clientId);
        }
        $totalDebit = (float) $totals->sum('debit');
        $totalCredit = (float) $totals->sum('credit');

        $balanceBefore = $this->balanceBefore($account->id, $dateFrom, $storeIds, $storeId, $clientId);
        $balanceAfter = $balanceBefore + $totalDebit - $totalCredit;

        return view('chart-of-accounts.show', compact(
            'account', 'entries', 'stores', 'clients', 'dateFrom', 'dateTo', 'storeId', 'clientId',
            'totalDebit', 'totalCredit', 'balanceBefore', 'balanceAfter'
        ));
    }

    /** Оборотно-сальдовая ведомость за период. */
    public function turnoverBalance(Request $request): View
    {
        $storeIds = auth()->user()->allowedStoreIds();
        $stores = Store::whereIn('id', $storeIds)->where('is_active', true)->orderBy('name')->get();
        $clients = Client::orderBy('full_name')->get();

        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $storeId = $request->get('store_id');
        $clientId = $request->get('client_id') ? (int) $request->get('client_id') : null;

        $accounts = Account::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();

        $rows = [];
        foreach ($accounts as $account) {
            $q = LedgerEntry::where('account_id', $account->id);
            if ($storeId && in_array((int) $storeId, $storeIds, true)) {
                $q->where('store_id', $storeId);
            } else {
                $q->where(function ($q2) use ($storeIds) {
                    $q2->whereIn('store_id', $storeIds)->orWhereNull('store_id');
                });
            }
            if ($clientId) {
                $q->where('client_id', $clientId);
            }

            $balanceBefore = $this->balanceBefore($account->id, $dateFrom, $storeIds, $storeId, $clientId);
            $turnover = $q->whereBetween('entry_date', [$dateFrom, $dateTo])
                ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
                ->first();
            $debit = (float) ($turnover->debit ?? 0);
            $credit = (float) ($turnover->credit ?? 0);
            $balanceAfter = $balanceBefore + $debit - $credit;

            if ($balanceBefore != 0 || $debit != 0 || $credit != 0 || $balanceAfter != 0) {
                $byClient = $this->turnoverByClient($account->id, $dateFrom, $dateTo, $storeIds, $storeId, $clientId);
                $rows[] = (object) [
                    'account' => $account,
                    'balance_before' => $balanceBefore,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance_after' => $balanceAfter,
                    'by_client' => $byClient,
                ];
            }
        }

        return view('chart-of-accounts.turnover-balance', compact(
            'accounts', 'rows', 'stores', 'clients', 'dateFrom', 'dateTo', 'storeId', 'clientId'
        ));
    }

    /** Сальдо по счёту на дату (до начала периода). */
    private function balanceBefore(int $accountId, string $dateFrom, array $storeIds, ?string $storeId, ?int $clientId = null): float
    {
        $q = LedgerEntry::where('account_id', $accountId)->where('entry_date', '<', $dateFrom);
        if ($storeId && in_array((int) $storeId, $storeIds, true)) {
            $q->where('store_id', $storeId);
        } else {
            $q->where(function ($q2) use ($storeIds) {
                $q2->whereIn('store_id', $storeIds)->orWhereNull('store_id');
            });
        }
        if ($clientId) {
            $q->where('client_id', $clientId);
        }
        $sum = $q->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')->value('balance');
        return (float) ($sum ?? 0);
    }

    /** Обороты по счёту в разрезе клиентов за период (для раскрытия ОСВ). */
    private function turnoverByClient(int $accountId, string $dateFrom, string $dateTo, array $storeIds, ?string $storeId, ?int $clientId = null): array
    {
        $q = LedgerEntry::where('account_id', $accountId)->whereBetween('entry_date', [$dateFrom, $dateTo]);
        if ($storeId && in_array((int) $storeId, $storeIds, true)) {
            $q->where('store_id', $storeId);
        } else {
            $q->where(function ($q2) use ($storeIds) {
                $q2->whereIn('store_id', $storeIds)->orWhereNull('store_id');
            });
        }
        if ($clientId) {
            $q->where('client_id', $clientId);
        }
        $totals = $q->selectRaw('client_id, COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->groupBy('client_id')
            ->get();

        $result = [];
        foreach ($totals as $t) {
            $cid = $t->client_id;
            $balanceBefore = $this->balanceBefore($accountId, $dateFrom, $storeIds, $storeId, $cid);
            $d = (float) $t->debit;
            $c = (float) $t->credit;
            $balanceAfter = $balanceBefore + $d - $c;
            $client = $cid ? Client::find($cid) : null;
            $result[] = (object) [
                'client_id' => $cid,
                'client_name' => $client ? $client->full_name : '— Без привязки к клиенту',
                'balance_before' => $balanceBefore,
                'debit' => $d,
                'credit' => $c,
                'balance_after' => $balanceAfter,
            ];
        }
        return $result;
    }
}
