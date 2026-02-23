<?php

namespace App\Http\Controllers;

use App\Models\DocumentLedgerTemplate;
use App\Models\LedgerEntry;
use App\Models\PurchaseContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Список договоров скупки, просмотр, печать. */
class PurchaseContractController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseContract::with(['client', 'item', 'store']);
        $query->whereIn('store_id', Auth::user()->allowedStoreIds());

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        $contracts = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('purchase-contracts.index', compact('contracts'));
    }

    public function show(PurchaseContract $purchaseContract)
    {
        if (! in_array($purchaseContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $purchaseContract->load(['client', 'item.status', 'store', 'appraiser']);

        $ledgerEntries = LedgerEntry::where('document_type', 'purchase_contract')
            ->where('document_id', $purchaseContract->id)
            ->with('account')
            ->orderBy('id')
            ->get();
        $templates = DocumentLedgerTemplate::forDocumentType('purchase_contract');
        $documentType = 'purchase_contract';
        $documentId = $purchaseContract->id;

        return view('purchase-contracts.show', compact(
            'purchaseContract', 'ledgerEntries', 'templates', 'documentType', 'documentId'
        ));
    }

    /** Печатная форма договора скупки. */
    public function print(PurchaseContract $purchaseContract)
    {
        if (! in_array($purchaseContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $purchaseContract->load(['client', 'item', 'store', 'appraiser']);

        return view('purchase-contracts.print', compact('purchaseContract'));
    }
}
