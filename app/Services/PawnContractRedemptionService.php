<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\PawnContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Выкуп залога и оплата процентов (пролонгация). */
class PawnContractRedemptionService
{
    public function redeem(User $user, PawnContract $pawnContract): PawnContract
    {
        $this->assertCanRedeem($user, $pawnContract);

        DB::beginTransaction();
        try {
            $pawnContract->update([
                'is_redeemed' => true,
                'redeemed_at' => now(),
                'redeemed_by' => $user->id,
            ]);

            $buybackAmount = (float) $pawnContract->buyback_amount;
            $loanAmount = (float) $pawnContract->loan_amount;
            $interestAmount = round($buybackAmount - $loanAmount, 2);
            $entryDate = now();
            $commentBase = 'Выкуп по договору залога №'.$pawnContract->contract_number;

            $cashDoc = $this->createIncomeCashDocument(
                $pawnContract,
                $user,
                $buybackAmount,
                $commentBase,
                'Возврат займа'
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->postRedeemLedger($pawnContract, $cashDoc, $loanAmount, $interestAmount, $entryDate);

        return $pawnContract->fresh(['client', 'item']);
    }

    /**
     * Оплата процентов без выкупа — продление срока залога.
     *
     * @param  array{extend_days?: int}  $options
     */
    public function payInterest(User $user, PawnContract $pawnContract, array $options = []): PawnContract
    {
        $this->assertCanRedeem($user, $pawnContract);

        $interestAmount = $this->interestAmount($pawnContract);
        if ($interestAmount <= 0) {
            throw ValidationException::withMessages([
                'loan_percent' => 'Сумма процентов для оплаты равна нулю.',
            ]);
        }

        $extendDays = max(1, (int) ($options['extend_days'] ?? 30));
        $entryDate = now();

        DB::beginTransaction();
        try {
            $baseDate = $pawnContract->expiry_date
                ? Carbon::parse($pawnContract->expiry_date)->max(now()->startOfDay())
                : now()->startOfDay();
            $newExpiry = $baseDate->copy()->addDays($extendDays);

            $pawnContract->update([
                'expiry_date' => $newExpiry->format('Y-m-d'),
            ]);

            $cashDoc = $this->createIncomeCashDocument(
                $pawnContract,
                $user,
                $interestAmount,
                'Оплата процентов по договору залога №'.$pawnContract->contract_number,
                'Возврат займа'
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($cashDoc) {
            app(LedgerService::class)->post(
                Account::CODE_CASH,
                Account::CODE_OTHER_INCOME,
                $interestAmount,
                $entryDate,
                $pawnContract->store_id,
                'cash_document',
                $cashDoc->id,
                'Проценты по договору залога №'.$pawnContract->contract_number,
                $pawnContract->client_id
            );
        }

        return $pawnContract->fresh(['client', 'item']);
    }

    private function assertCanRedeem(User $user, PawnContract $pawnContract): void
    {
        if (! in_array($pawnContract->store_id, $user->allowedStoreIds(), true)) {
            throw ValidationException::withMessages(['store_id' => 'Нет доступа к этой точке.']);
        }
        if (! $user->canProcessSales()) {
            throw ValidationException::withMessages([
                'role' => 'Нет прав на выкуп и оплату процентов (нужна роль кассир или менеджер).',
            ]);
        }
        if ($pawnContract->is_redeemed) {
            throw ValidationException::withMessages(['is_redeemed' => 'Договор уже выкуплен.']);
        }
    }

    public function interestAmount(PawnContract $pawnContract): float
    {
        $loanAmount = (float) $pawnContract->loan_amount;
        $buyback = (float) ($pawnContract->buyback_amount ?: 0);
        if ($buyback <= 0) {
            $buyback = $loanAmount + ($loanAmount * (float) $pawnContract->loan_percent / 100);
        }

        return round(max(0, $buyback - $loanAmount), 2);
    }

    private function createIncomeCashDocument(
        PawnContract $pawnContract,
        User $user,
        float $amount,
        string $comment,
        string $operationName,
    ): ?CashDocument {
        if ($amount <= 0) {
            return null;
        }

        $opType = CashOperationType::findByName($operationName);
        if (! $opType) {
            return null;
        }

        $docNum = CashDocument::generateDocumentNumber($pawnContract->store_id, 'income');

        return CashDocument::create([
            'store_id' => $pawnContract->store_id,
            'client_id' => $pawnContract->client_id,
            'operation_type_id' => $opType->id,
            'document_number' => $docNum,
            'document_date' => now()->format('Y-m-d'),
            'amount' => $amount,
            'comment' => $comment,
            'created_by' => $user->id,
        ]);
    }

    private function postRedeemLedger(
        PawnContract $pawnContract,
        ?CashDocument $cashDoc,
        float $loanAmount,
        float $interestAmount,
        Carbon $entryDate,
    ): void {
        $ledger = app(LedgerService::class);
        $docType = $cashDoc ? 'cash_document' : 'pawn_contract';
        $docId = $cashDoc ? $cashDoc->id : $pawnContract->id;
        $clientId = $pawnContract->client_id;

        if ($cashDoc && $loanAmount > 0) {
            $ledger->post(
                Account::CODE_CASH,
                Account::CODE_LOANS,
                $loanAmount,
                $entryDate,
                $pawnContract->store_id,
                $docType,
                $docId,
                'Возврат основного долга по договору №'.$pawnContract->contract_number,
                $clientId
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
                'Проценты по договору залога №'.$pawnContract->contract_number,
                $clientId
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
                'Возврат товара из залога №'.$pawnContract->contract_number,
                $clientId
            );
        }
    }
}
