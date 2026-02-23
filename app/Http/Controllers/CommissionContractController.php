<?php

namespace App\Http\Controllers;

use App\Models\CommissionContract;
use App\Models\DocumentLedgerTemplate;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Список договоров комиссии, просмотр, продажа. */
class CommissionContractController extends Controller
{
    public function index(Request $request)
    {
        $query = CommissionContract::with(['client', 'item', 'store']);
        $query->whereIn('store_id', Auth::user()->allowedStoreIds());

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('sold')) {
            if ($request->sold === '1') {
                $query->where('is_sold', true);
            } else {
                $query->where('is_sold', false);
            }
        }
        $contracts = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('commission-contracts.index', compact('contracts'));
    }

    public function show(CommissionContract $commissionContract)
    {
        if (! in_array($commissionContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $commissionContract->load(['client', 'item.status', 'store', 'appraiser', 'soldByUser']);

        $ledgerEntries = LedgerEntry::where('document_type', 'commission_contract')
            ->where('document_id', $commissionContract->id)
            ->with('account')
            ->orderBy('id')
            ->get();
        $templates = DocumentLedgerTemplate::forDocumentType('commission_contract');
        $documentType = 'commission_contract';
        $documentId = $commissionContract->id;

        return view('commission-contracts.show', compact(
            'commissionContract', 'ledgerEntries', 'templates', 'documentType', 'documentId'
        ));
    }

    /** Печатная форма договора комиссии. */
    public function print(CommissionContract $commissionContract)
    {
        if (! in_array($commissionContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $commissionContract->load(['client', 'item', 'store', 'appraiser']);

        return view('commission-contracts.print', compact('commissionContract'));
    }

    /** Оформить продажу. */
    public function markSold(Request $request, CommissionContract $commissionContract)
    {
        if (! in_array($commissionContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if (! Auth::user()->canProcessSales()) {
            abort(403, 'Нет прав на оформление продажи.');
        }
        if ($commissionContract->is_sold) {
            return redirect()->route('commission-contracts.show', $commissionContract)->with('error', 'Товар уже продан.');
        }

        $commissionContract->update([
            'is_sold' => true,
            'sold_at' => now(),
            'sold_by' => Auth::id(),
        ]);

        $amount = (float) $commissionContract->seller_price;
        if ($amount > 0) {
            app(LedgerService::class)->post(
                \App\Models\Account::CODE_CASH,
                \App\Models\Account::CODE_SALES,
                $amount,
                now(),
                $commissionContract->store_id,
                'commission_contract',
                $commissionContract->id,
                'Продажа по договору комиссии №' . $commissionContract->contract_number,
                $commissionContract->client_id
            );
        }

        return redirect()->route('commission-contracts.show', $commissionContract)->with('success', 'Продажа оформлена.');
    }
}
