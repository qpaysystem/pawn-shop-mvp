<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\ItemStatus;
use App\Models\PawnContract;
use App\Services\LedgerService;
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

        return view('pawn-contracts.show', compact('pawnContract'));
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
    public function redeem(Request $request, PawnContract $pawnContract)
    {
        if (! in_array($pawnContract->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if (! Auth::user()->canProcessSales()) {
            abort(403, 'Нет прав на оформление выкупа.');
        }
        if ($pawnContract->is_redeemed) {
            return redirect()->route('pawn-contracts.show', $pawnContract)->with('error', 'Договор уже выкуплен.');
        }

        $pawnContract->update([
            'is_redeemed' => true,
            'redeemed_at' => now(),
            'redeemed_by' => Auth::id(),
        ]);

        $buybackAmount = (float) $pawnContract->buyback_amount;
        $loanAmount = (float) $pawnContract->loan_amount;
        $interestAmount = round($buybackAmount - $loanAmount, 2);
        $entryDate = now();
        $commentBase = 'Выкуп по договору залога №' . $pawnContract->contract_number;

        $repayOpType = CashOperationType::findByName('Возврат займа');
        $cashDoc = null;
        if ($repayOpType && $buybackAmount > 0) {
            $docNum = CashDocument::generateDocumentNumber($pawnContract->store_id, 'income');
            $cashDoc = CashDocument::create([
                'store_id' => $pawnContract->store_id,
                'client_id' => $pawnContract->client_id,
                'operation_type_id' => $repayOpType->id,
                'document_number' => $docNum,
                'document_date' => $entryDate->format('Y-m-d'),
                'amount' => $buybackAmount,
                'comment' => $commentBase,
                'created_by' => Auth::id(),
            ]);
        }

        $ledger = app(LedgerService::class);
        $docType = $cashDoc ? 'cash_document' : 'pawn_contract';
        $docId = $cashDoc ? $cashDoc->id : $pawnContract->id;

        if ($cashDoc && $loanAmount > 0) {
            $ledger->post(
                Account::CODE_CASH,
                Account::CODE_LOANS,
                $loanAmount,
                $entryDate,
                $pawnContract->store_id,
                $docType,
                $docId,
                'Возврат основного долга по договору №' . $pawnContract->contract_number
            );
        }
        if ($cashDoc && $interestAmount > 0) {
            $ledger->post(
                Account::CODE_CASH,
                Account::CODE_OTHER_INCOME,
                $interestAmount,
                $entryDate,
                $pawnContract->store_id,
                $docType,
                $docId,
                'Проценты по договору залога №' . $pawnContract->contract_number
            );
        }
        if ($loanAmount > 0) {
            $ledger->post(
                Account::CODE_SETTLEMENTS_OTHER,
                Account::CODE_PLEDGE,
                $loanAmount,
                $entryDate,
                $pawnContract->store_id,
                'pawn_contract',
                $pawnContract->id,
                'Возврат товара из залога №' . $pawnContract->contract_number
            );
        }

        if ($request->get('from') === 'accept') {
            return redirect()->route('accept.create')->with('success', 'Выкуп оформлен. Кассовый документ создан, проводки отражены в ОСВ.');
        }

        return redirect()->route('pawn-contracts.show', $pawnContract)->with('success', 'Выкуп оформлен.');
    }
}
