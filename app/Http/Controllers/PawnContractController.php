<?php

namespace App\Http\Controllers;

use App\Models\DocumentLedgerTemplate;
use App\Models\LedgerEntry;
use App\Models\PawnContract;
use App\Services\PawnContractRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Список договоров залога, просмотр, выкуп. */
class PawnContractController extends Controller
{
    public function index(Request $request)
    {
        $query = PawnContract::with(['client', 'item', 'store']);
        $query->whereIn('store_id', Auth::user()->allowedStoreIds());

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('redeemed')) {
            if ($request->redeemed === '1') {
                $query->where('is_redeemed', true);
            } else {
                $query->where('is_redeemed', false);
            }
        }
        $contracts = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('pawn-contracts.index', compact('contracts'));
    }

    public function show(PawnContract $pawnContract)
    {
        if (! in_array($pawnContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $pawnContract->load(['client', 'item.status', 'store', 'appraiser', 'redeemedByUser']);

        $ledgerEntries = LedgerEntry::where('document_type', 'pawn_contract')
            ->where('document_id', $pawnContract->id)
            ->with('account')
            ->orderBy('id')
            ->get();
        $templates = DocumentLedgerTemplate::forDocumentType('pawn_contract');
        $documentType = 'pawn_contract';
        $documentId = $pawnContract->id;

        return view('pawn-contracts.show', compact(
            'pawnContract', 'ledgerEntries', 'templates', 'documentType', 'documentId'
        ));
    }

    /** Печатная форма договора залога. */
    public function print(PawnContract $pawnContract)
    {
        if (! in_array($pawnContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $pawnContract->load(['client', 'item', 'store', 'appraiser']);

        return view('pawn-contracts.print', compact('pawnContract'));
    }

    /** Оформить выкуп. */
    public function redeem(Request $request, PawnContract $pawnContract, PawnContractRedemptionService $redemptionService)
    {
        $redemptionService->redeem(Auth::user(), $pawnContract);

        if ($request->get('from') === 'accept') {
            return redirect()->route('accept.create')->with('success', 'Выкуп оформлен. Кассовый документ создан, проводки отражены в ОСВ.');
        }

        return redirect()->route('pawn-contracts.show', $pawnContract)->with('success', 'Выкуп оформлен.');
    }
}
