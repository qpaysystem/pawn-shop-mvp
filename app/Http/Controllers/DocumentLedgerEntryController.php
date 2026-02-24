<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashDocument;
use App\Models\CommissionContract;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\PawnContract;
use App\Models\PayrollAccrual;
use App\Models\PurchaseContract;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/** Ручное добавление проводок к документу (вкладка «Бухгалтерские проводки»). */
class DocumentLedgerEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'redirect_to' => 'nullable|string|max:2000',
            'document_type' => 'required|string|in:expense,cash_document,payroll_accrual,pawn_contract,commission_contract,purchase_contract',
            'document_id' => 'required|integer|min:1',
            'debit_account_code' => 'required|string|max:20|exists:accounts,code',
            'credit_account_code' => 'required|string|max:20|exists:accounts,code',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'nullable|date',
            'comment' => 'nullable|string|max:500',
        ]);

        $context = $this->resolveDocumentContext($validated['document_type'], (int) $validated['document_id']);
        if ($context === null) {
            return redirect()->to($request->input('redirect_to', url()->previous()))
                ->withInput()
                ->withErrors(['document_id' => 'Нет доступа к документу или документ не найден.']);
        }

        $entryDate = ! empty($validated['entry_date'])
            ? Carbon::parse($validated['entry_date'])
            : now();
        $comment = trim((string) ($validated['comment'] ?? ''));

        app(LedgerService::class)->post(
            $validated['debit_account_code'],
            $validated['credit_account_code'],
            (float) $validated['amount'],
            $entryDate,
            $context['store_id'],
            $validated['document_type'],
            (int) $validated['document_id'],
            $comment !== '' ? $comment : null,
            $context['client_id']
        );

        $redirectTo = $request->input('redirect_to', route('expenses.index'));
        return redirect()->to($redirectTo)->with('success', 'Проводка добавлена.');
    }

    /** Изменить счёт в проводке (отражается в ОСВ). */
    public function update(Request $request, LedgerEntry $ledgerEntry): RedirectResponse
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:20|exists:accounts,code',
            'redirect_to' => 'nullable|string|max:2000',
        ]);

        $context = $this->resolveDocumentContext($ledgerEntry->document_type, (int) $ledgerEntry->document_id);
        if ($context === null) {
            return redirect()->to($request->input('redirect_to', url()->previous()))
                ->withErrors(['account_code' => 'Нет доступа к документу или документ не найден.']);
        }

        $account = Account::findByCode($validated['account_code']);
        if (! $account) {
            return redirect()->to($request->input('redirect_to', url()->previous()))
                ->withErrors(['account_code' => 'Счёт не найден.']);
        }

        $ledgerEntry->update(['account_id' => $account->id]);

        $redirectTo = $request->input('redirect_to', url()->previous());
        return redirect()->to($redirectTo)->with('success', 'Счёт в проводке изменён. Изменения отражены в ОСВ.');
    }

    /** Получить store_id и client_id по документу и проверить доступ. */
    private function resolveDocumentContext(string $documentType, int $documentId): ?array
    {
        $storeIds = auth()->user()->allowedStoreIds();

        $storeId = null;
        $clientId = null;

        switch ($documentType) {
            case 'expense':
                $doc = Expense::find($documentId);
                if (! $doc || ($doc->store_id && ! in_array($doc->store_id, $storeIds, true))) {
                    return null;
                }
                $storeId = $doc->store_id;
                $clientId = $doc->client_id;
                break;
            case 'cash_document':
                $doc = CashDocument::find($documentId);
                if (! $doc || ! in_array($doc->store_id, $storeIds, true)) {
                    return null;
                }
                $storeId = $doc->store_id;
                $clientId = $doc->client_id;
                break;
            case 'payroll_accrual':
                $doc = PayrollAccrual::find($documentId);
                if (! $doc) {
                    return null;
                }
                break;
            case 'pawn_contract':
                $doc = PawnContract::find($documentId);
                if (! $doc || ! in_array($doc->store_id, $storeIds, true)) {
                    return null;
                }
                $storeId = $doc->store_id;
                $clientId = $doc->client_id;
                break;
            case 'commission_contract':
                $doc = CommissionContract::find($documentId);
                if (! $doc || ! in_array($doc->store_id, $storeIds, true)) {
                    return null;
                }
                $storeId = $doc->store_id;
                $clientId = $doc->client_id;
                break;
            case 'purchase_contract':
                $doc = PurchaseContract::find($documentId);
                if (! $doc || ! in_array($doc->store_id, $storeIds, true)) {
                    return null;
                }
                $storeId = $doc->store_id;
                $clientId = $doc->client_id;
                break;
            default:
                return null;
        }

        return ['store_id' => $storeId, 'client_id' => $clientId];
    }
}
