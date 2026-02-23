<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\Auth;

/** Сервис создания бухгалтерских проводок. */
class LedgerService
{
    /**
     * Создать проводку: дебет одного счёта, кредит другого (одинаковая сумма).
     */
    public function post(
        string $debitAccountCode,
        string $creditAccountCode,
        float $amount,
        \DateTimeInterface $entryDate,
        ?int $storeId = null,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $comment = null,
        ?int $clientId = null
    ): void {
        if ($amount <= 0) {
            return;
        }

        $debitAccount = Account::findByCode($debitAccountCode);
        $creditAccount = Account::findByCode($creditAccountCode);
        if (! $debitAccount || ! $creditAccount) {
            return;
        }

        LedgerEntry::create([
            'account_id' => $debitAccount->id,
            'store_id' => $storeId,
            'client_id' => $clientId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'entry_date' => $entryDate,
            'debit' => $amount,
            'credit' => 0,
            'comment' => $comment,
            'created_by' => Auth::id(),
        ]);

        LedgerEntry::create([
            'account_id' => $creditAccount->id,
            'store_id' => $storeId,
            'client_id' => $clientId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'entry_date' => $entryDate,
            'debit' => 0,
            'credit' => $amount,
            'comment' => $comment,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Перемещение между кассами: кредит 50 по магазину-источнику, дебет 50 по магазину-получателю.
     */
    public function postTransfer(
        float $amount,
        \DateTimeInterface $entryDate,
        int $storeIdFrom,
        int $storeIdTo,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $comment = null,
        ?int $clientId = null
    ): void {
        if ($amount <= 0) {
            return;
        }
        $account = Account::findByCode(Account::CODE_CASH);
        if (! $account) {
            return;
        }
        $userId = Auth::id();
        LedgerEntry::create([
            'account_id' => $account->id,
            'store_id' => $storeIdFrom,
            'client_id' => $clientId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'entry_date' => $entryDate,
            'debit' => 0,
            'credit' => $amount,
            'comment' => $comment,
            'created_by' => $userId,
        ]);
        LedgerEntry::create([
            'account_id' => $account->id,
            'store_id' => $storeIdTo,
            'client_id' => $clientId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'entry_date' => $entryDate,
            'debit' => $amount,
            'credit' => 0,
            'comment' => $comment,
            'created_by' => $userId,
        ]);
    }

    /**
     * Одна сторона проводки (только дебет или только кредит по одному счёту).
     * Вторая сторона вызывающий код создаёт отдельно или через второй вызов.
     */
    public function postSingle(
        string $accountCode,
        float $debit,
        float $credit,
        \DateTimeInterface $entryDate,
        ?int $storeId = null,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $comment = null,
        ?int $clientId = null
    ): void {
        $account = Account::findByCode($accountCode);
        if (! $account || ($debit <= 0 && $credit <= 0)) {
            return;
        }

        LedgerEntry::create([
            'account_id' => $account->id,
            'store_id' => $storeId,
            'client_id' => $clientId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'entry_date' => $entryDate,
            'debit' => $debit,
            'credit' => $credit,
            'comment' => $comment,
            'created_by' => Auth::id(),
        ]);
    }
}
