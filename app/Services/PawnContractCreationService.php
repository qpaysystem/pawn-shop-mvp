<?php

namespace App\Services;

use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\Client;
use App\Models\ClientVisit;
use App\Models\Item;
use App\Models\ItemStatusHistory;
use App\Models\PawnContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Создание договора залога (web accept + mobile API v1). */
class PawnContractCreationService
{
    /**
     * @param  array<string, mixed>  $data  Mobile CreatePawnContractPayload shape
     * @param  array<int, UploadedFile>  $photoFiles
     */
    public function create(User $user, array $data, array $photoFiles = []): PawnContract
    {
        if (! $user->canCreateContracts()) {
            throw ValidationException::withMessages(['store_id' => 'Нет прав на приём залога.']);
        }

        $storeId = (int) $data['store_id'];
        if (! in_array($storeId, $user->allowedStoreIds(), true)) {
            throw ValidationException::withMessages(['store_id' => 'Недоступная точка.']);
        }

        $visitPurpose = in_array($data['visit_purpose'] ?? 'appraisal', ['appraisal', 'redemption', 'non_target', 'identification'], true)
            ? ($data['visit_purpose'] ?? 'appraisal')
            : 'appraisal';

        $itemData = $data['item'] ?? [];
        $loanData = $data['loan'] ?? [];

        DB::beginTransaction();
        try {
            $client = $this->resolveClient($data);
            $photos = $this->storePhotos($photoFiles);

            $initialPrice = isset($itemData['initial_price']) ? (float) $itemData['initial_price'] : null;
            $currentPrice = isset($itemData['current_price'])
                ? (float) $itemData['current_price']
                : ($initialPrice ?? 0.0);

            $item = Item::create([
                'name' => $itemData['name'],
                'description' => $itemData['description'] ?? null,
                'category_id' => ! empty($itemData['category_id']) ? (int) $itemData['category_id'] : null,
                'brand_id' => ! empty($itemData['brand_id']) ? (int) $itemData['brand_id'] : null,
                'store_id' => $storeId,
                'storage_location_id' => ! empty($itemData['storage_location_id'])
                    ? (int) $itemData['storage_location_id']
                    : null,
                'status_id' => (int) $itemData['status_id'],
                'barcode' => Item::generateBarcode(),
                'photos' => $photos !== [] ? $photos : null,
                'initial_price' => $initialPrice,
                'current_price' => $currentPrice,
            ]);

            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => null,
                'new_status_id' => $item->status_id,
                'changed_by' => $user->id,
            ]);

            $loanAmount = (float) $loanData['loan_amount'];
            $percent = (float) ($loanData['loan_percent'] ?? 0);
            $buyback = $loanAmount + ($loanAmount * $percent / 100);
            $loanDate = $loanData['loan_date'];
            $expiryDate = $loanData['expiry_date'];

            $contract = PawnContract::create([
                'contract_number' => PawnContract::generateContractNumber(),
                'client_id' => $client->id,
                'item_id' => $item->id,
                'appraiser_id' => $user->id,
                'store_id' => $storeId,
                'loan_amount' => $loanAmount,
                'loan_percent' => $percent,
                'loan_date' => $loanDate,
                'expiry_date' => $expiryDate,
                'buyback_amount' => $buyback,
            ]);

            $loanOpType = CashOperationType::findByName('Выдача займа');
            if ($loanOpType) {
                $docNum = CashDocument::generateDocumentNumber($storeId, 'expense');
                CashDocument::create([
                    'store_id' => $storeId,
                    'client_id' => $client->id,
                    'operation_type_id' => $loanOpType->id,
                    'document_number' => $docNum,
                    'document_date' => $loanDate,
                    'amount' => $loanAmount,
                    'comment' => 'Договор залога №'.$contract->contract_number,
                    'created_by' => $user->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->createClientVisit($storeId, $client->id, $visitPurpose, $contract->id, $user->id);

        $ledger = app(LedgerService::class);
        $parsedLoanDate = Carbon::parse($loanDate);
        $ledger->post(
            \App\Models\Account::CODE_LOANS,
            \App\Models\Account::CODE_CASH,
            $loanAmount,
            $parsedLoanDate,
            $storeId,
            'pawn_contract',
            $contract->id,
            'Договор залога №'.$contract->contract_number,
            $client->id
        );
        $ledger->post(
            \App\Models\Account::CODE_PLEDGE,
            \App\Models\Account::CODE_SETTLEMENTS_OTHER,
            $loanAmount,
            $parsedLoanDate,
            $storeId,
            'pawn_contract',
            $contract->id,
            'Поступление товара в залог №'.$contract->contract_number,
            $client->id
        );

        return $contract->load(['client', 'item']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            return Client::findOrFail((int) $data['client_id']);
        }

        $clientInput = $data['client'] ?? null;
        if (! is_array($clientInput)) {
            throw ValidationException::withMessages(['client' => 'Укажите client_id или client.']);
        }

        $phone = trim((string) ($clientInput['phone'] ?? ''));
        $lastName = trim((string) ($clientInput['last_name'] ?? ''));
        $firstName = trim((string) ($clientInput['first_name'] ?? ''));
        $patronymic = trim((string) ($clientInput['patronymic'] ?? ''));

        if ($phone === '' || $lastName === '' || $firstName === '') {
            throw ValidationException::withMessages(['client' => 'Фамилия, имя и телефон обязательны.']);
        }

        $phoneDigits = preg_replace('/\D/', '', $phone);
        $existing = Client::query()
            ->where('phone', $phone)
            ->orWhere('phone', $phoneDigits)
            ->first();

        $fullName = trim(implode(' ', array_filter([$lastName, $firstName, $patronymic])));
        $row = [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'patronymic' => $patronymic ?: null,
            'full_name' => $fullName,
            'passport_data' => $clientInput['passport_data'] ?? null,
        ];

        if ($existing) {
            $existing->update($row);

            return $existing->fresh();
        }

        return Client::create(array_merge($row, [
            'client_type' => Client::TYPE_INDIVIDUAL,
            'phone' => $phone,
            'phone_key' => Client::phoneToKey($phone),
            'blacklist_flag' => false,
        ]));
    }

    /**
     * @param  array<int, UploadedFile>  $photoFiles
     * @return array<int, string>
     */
    private function storePhotos(array $photoFiles): array
    {
        $paths = [];
        foreach ($photoFiles as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $paths[] = $file->store('items', 'public');
        }

        return $paths;
    }

    private function createClientVisit(int $storeId, int $clientId, string $visitPurpose, int $contractId, int $userId): void
    {
        try {
            ClientVisit::create([
                'store_id' => $storeId,
                'client_id' => $clientId,
                'visit_purpose' => $visitPurpose,
                'visited_at' => now(),
                'created_by' => $userId,
                'pawn_contract_id' => $contractId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClientVisit не создан для mobile pawn', [
                'pawn_contract_id' => $contractId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
