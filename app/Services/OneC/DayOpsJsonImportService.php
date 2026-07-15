<?php

namespace App\Services\OneC;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\CallCenterContact;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\Client;
use App\Models\ClientVisit;
use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\LmbProductEvent;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\SaleContract;
use App\Models\Store;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Импорт ежедневной выгрузки 1С (ДанныеИз1С_DD_MM_YYYY.json)
 * в БД ломбард-портала: залоги/выкупы, скупка, продажи, ПКО/РКО, банк,
 * события по товару (перемещения, смена статуса),
 * CRM-события (личные встречи, ревизии; звонки пропускаются — MTS).
 */
class DayOpsJsonImportService
{
    /** @var array<string, int> */
    private array $stats = [];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, Store> */
    private array $storeCache = [];

    /** @var array<string, Client> */
    private array $clientCache = [];

    /** @var array<string, CashOperationType> */
    private array $cashTypeCache = [];

    /** @var array<string, ItemStatus> */
    private array $statusCache = [];

    private ?int $defaultStoreId = null;

    private bool $dryRun = false;

    private bool $refreshProductEvents = false;

    private ?LedgerService $ledger = null;

    /**
     * @return array{ok: bool, stats: array<string, int>, errors: list<string>, warnings: list<string>, total: int>
     */
    public function import(string $filePath, bool $dryRun = false, bool $refreshProductEvents = false): array
    {
        $this->dryRun = $dryRun;
        $this->refreshProductEvents = $refreshProductEvents;
        $this->reset();
        $this->ledger = app(LedgerService::class);
        $this->ensureCashOperationTypes();

        if (! is_readable($filePath)) {
            $this->errors[] = "Файл не читается: {$filePath}";

            return $this->result(0);
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            $this->errors[] = 'Не удалось прочитать файл.';

            return $this->result(0);
        }

        // UTF-8 BOM из выгрузки 1С
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->errors[] = 'Некорректный JSON.';

            return $this->result(0);
        }

        // Сначала договоры и деньги, затем события по товару; CRM пропускаем.
        $priority = [
            'ОперацияПоЗалогу' => 10,
            'СкупкаЦенностей' => 20,
            'Реализация' => 30,
            'ПриходныйКассовыйОрдер' => 40,
            'РасходныйКассовыйОрдер' => 50,
            'ПоступлениеНаРасчетныйСчет' => 60,
            'СписаниеСРасчетногоСчета' => 70,
            'СобытияПоТовару' => 80,
            'Событие' => 90,
        ];

        usort($data, function ($a, $b) use ($priority) {
            $pa = $priority[$a['ТипДокумента'] ?? ''] ?? 1000;
            $pb = $priority[$b['ТипДокумента'] ?? ''] ?? 1000;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) ($a['Дата'] ?? ''), (string) ($b['Дата'] ?? ''));
        });

        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = (string) ($row['ТипДокумента'] ?? '');
            try {
                match ($type) {
                    'ОперацияПоЗалогу' => $this->importPawnOp($row),
                    'СкупкаЦенностей' => $this->importPurchase($row),
                    'Реализация' => $this->importSale($row),
                    'ПриходныйКассовыйОрдер' => $this->importCash($row, 'income'),
                    'РасходныйКассовыйОрдер' => $this->importCash($row, 'expense'),
                    'ПоступлениеНаРасчетныйСчет' => $this->importBank($row, true),
                    'СписаниеСРасчетногоСчета' => $this->importBank($row, false),
                    'СобытияПоТовару' => $this->importProductEvent($row),
                    'Событие' => $this->importCrmEvent($row),
                    default => $this->bump('skipped_other'),
                };
            } catch (\Throwable $e) {
                $num = (string) ($row['Номер'] ?? '?');
                $this->errors[] = "{$type} {$num}: ".$e->getMessage();
                $this->bump('errors');
            }
        }

        return $this->result(count($data));
    }

    private function importPawnOp(array $row): void
    {
        $kind = (string) ($row['ВидОперации'] ?? '');
        $ticket = trim((string) ($row['ЗалоговыйБилет'] ?? ''));
        $number = trim((string) ($row['Номер'] ?? ''));
        $at = $this->parseDate($row['Дата'] ?? $row['ДатаОперации'] ?? null);
        $store = $this->resolveStore((string) ($row['Филиал'] ?? ''));
        $client = $this->resolveContragent($row['Залогодатель'] ?? null);
        $amount = $this->money($row['ИтоговаяСуммаСсуды'] ?? null);
        $buyback = $this->money($row['СуммаВыкупа'] ?? null) ?: $amount;
        $percent = $this->percentFromScheme($row['СхемаКредитования'] ?? null);
        $expiry = $this->parseDate($row['ДатаВыкупа'] ?? null);

        $nom = $this->firstNomenclature($row['ТабличнаяЧасть'] ?? null);
        $item = $nom ? $this->resolveItem($nom, $store) : null;

        if ($kind === 'Залог') {
            $existing = $this->findPawn($ticket, $number);
            if ($existing) {
                $this->bump('pawn_exists');

                return;
            }
            if ($this->dryRun) {
                $this->bump('pawn_created');

                return;
            }
            if (! $client || ! $item || ! $store) {
                $this->warnings[] = "Залог {$number}: нет клиента/товара/точки — пропуск.";
                $this->bump('pawn_skipped');

                return;
            }
            PawnContract::create([
                'contract_number' => $ticket !== '' ? $ticket : $number,
                'lmb_doc_uid' => '1c-day:'.$number,
                'lmb_data' => [
                    'source' => 'day_ops_json',
                    'ticket' => $ticket,
                    'number' => $number,
                    'row' => $this->slimRow($row),
                ],
                'client_id' => $client->id,
                'item_id' => $item->id,
                'store_id' => $store->id,
                'loan_amount' => $amount ?: 0,
                'loan_percent' => $percent,
                'loan_date' => $at?->toDateString() ?? now()->toDateString(),
                'expiry_date' => $expiry?->toDateString(),
                'buyback_amount' => $buyback ?: null,
                'is_redeemed' => false,
            ]);
            $this->bump('pawn_created');

            return;
        }

        if ($kind === 'Выкуп') {
            $pawn = $this->findPawn($ticket, $number);
            if (! $pawn) {
                // создадим минимальный уже выкупленный, если есть данные
                if ($this->dryRun) {
                    $this->bump('redeem_created_missing');

                    return;
                }
                if ($client && $item && $store) {
                    $pawn = PawnContract::create([
                        'contract_number' => $ticket !== '' ? $ticket : $number,
                        'lmb_doc_uid' => '1c-day-redeem:'.$number,
                        'lmb_data' => ['source' => 'day_ops_json', 'ticket' => $ticket, 'number' => $number],
                        'client_id' => $client->id,
                        'item_id' => $item->id,
                        'store_id' => $store->id,
                        'loan_amount' => max(0, ($amount ?: 0) - $this->money($row['СуммаЗаКредит'] ?? null)),
                        'loan_percent' => $percent,
                        'loan_date' => $at?->copy()->subDays(30)->toDateString() ?? now()->toDateString(),
                        'expiry_date' => $expiry?->toDateString(),
                        'buyback_amount' => $buyback ?: $amount,
                        'is_redeemed' => true,
                        'redeemed_at' => $at,
                    ]);
                    $this->bump('redeem_created_missing');
                } else {
                    $this->warnings[] = "Выкуп {$number}/{$ticket}: договор не найден.";
                    $this->bump('redeem_not_found');

                    return;
                }
            } else {
                if ($pawn->is_redeemed) {
                    $this->bump('redeem_already');

                    return;
                }
                if ($this->dryRun) {
                    $this->bump('redeemed');

                    return;
                }
                $pawn->update([
                    'is_redeemed' => true,
                    'redeemed_at' => $at,
                    'buyback_amount' => $buyback ?: $pawn->buyback_amount,
                ]);
                $this->bump('redeemed');
            }

            return;
        }

        $this->bump('pawn_other');
    }

    private function importPurchase(array $row): void
    {
        $number = trim((string) ($row['Номер'] ?? ''));
        $ext = '1c:purchase:'.$number;
        if ($number !== '' && PurchaseContract::query()->where('contract_number', $number)->exists()) {
            $this->bump('purchase_exists');

            return;
        }
        if ($number !== '' && PurchaseContract::query()->where('lmb_doc_uid', $ext)->exists()) {
            $this->bump('purchase_exists');

            return;
        }

        $store = $this->resolveStore((string) ($row['Филиал'] ?? ''));
        $client = $this->resolveContragent($row['Контрагент'] ?? null);
        $nom = $this->firstNomenclature($row['ТабличнаяЧасть'] ?? null);
        $item = $nom ? $this->resolveItem($nom, $store) : null;
        $amount = $this->money($row['СуммаДокумента'] ?? ($nom['Цена'] ?? null));
        $at = $this->parseDate($row['Дата'] ?? null);

        if ($this->dryRun) {
            $this->bump('purchase_created');

            return;
        }
        if (! $store || ! $client || ! $item) {
            $this->warnings[] = "Скупка {$number}: нет точки/клиента/товара.";
            $this->bump('purchase_skipped');

            return;
        }

        PurchaseContract::create([
            'contract_number' => $number !== '' ? $number : ('S-1C-'.Str::random(8)),
            'lmb_doc_uid' => $ext,
            'lmb_data' => ['source' => 'day_ops_json', 'row' => $this->slimRow($row)],
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $store->id,
            'purchase_amount' => $amount ?: 0,
            'purchase_date' => $at?->toDateString() ?? now()->toDateString(),
        ]);
        $this->bump('purchase_created');
    }

    private function importSale(array $row): void
    {
        $number = trim((string) ($row['Номер'] ?? ''));
        $ext = '1c:sale:'.$number;
        if ($number !== '') {
            $exists = SaleContract::query()
                ->where(function ($q) use ($ext, $number) {
                    $q->where('external_id', $ext)->orWhere('contract_number', $number);
                })
                ->exists();
            if ($exists) {
                $this->bump('sale_exists');

                return;
            }
        }

        $store = $this->resolveStore((string) ($row['Филиал'] ?? ''));
        $client = $this->resolveContragent($row['Контрагент'] ?? null);
        if (! $client) {
            $client = $this->ensureAnonymousBuyer();
        }
        $nom = $this->firstNomenclature($row['ТабличнаяЧасть'] ?? null);
        $item = $nom ? $this->resolveItem($nom, $store) : null;
        $amount = $this->money($row['СуммаДокумента'] ?? ($nom['Сумма'] ?? $nom['Цена'] ?? null));
        $at = $this->parseDate($row['Дата'] ?? null);

        if ($this->dryRun) {
            $this->bump('sale_created');

            return;
        }
        if (! $store || ! $item || ! $client) {
            $this->warnings[] = "Реализация {$number}: нет точки/товара/клиента.";
            $this->bump('sale_skipped');

            return;
        }

        SaleContract::create([
            'contract_number' => $number !== '' ? $number : ('SALE-1C-'.Str::random(8)),
            'external_id' => $ext,
            'lmb_data' => ['source' => 'day_ops_json', 'row' => $this->slimRow($row)],
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $store->id,
            'sale_amount' => $amount ?: 0,
            'sale_date' => $at ?? now(),
        ]);
        $this->bump('sale_created');
    }

    private function importCash(array $row, string $direction): void
    {
        $number = trim((string) ($row['Номер'] ?? ''));
        $prefix = $direction === 'income' ? 'pko' : 'rko';
        $ext = '1c:'.$prefix.':'.$number;
        if ($number !== '' && CashDocument::query()->where('external_id', $ext)->exists()) {
            $this->bump('cash_exists');

            return;
        }

        $opName = $this->mapCashOperationName((string) ($row['ВидОперации'] ?? ''), $direction);
        $opType = $this->cashType($opName, $direction);
        $store = $this->resolveStore((string) ($row['Филиал'] ?? ''));
        $client = $this->resolveContragent($row['Контрагент'] ?? null);
        $amount = $this->money($row['СуммаДокумента'] ?? null);
        $at = $this->parseDate($row['Дата'] ?? null);
        $comment = trim(implode(' · ', array_filter([
            (string) ($row['Основание'] ?? ''),
            (string) ($row['Приложение'] ?? ''),
            (string) ($row['Комментарий'] ?? ''),
            '1С '.$number,
        ])));

        if ($amount === null || $amount <= 0) {
            $this->bump('cash_skipped');

            return;
        }
        if ($this->dryRun) {
            $this->bump('cash_created');

            return;
        }
        if (! $store) {
            $this->warnings[] = "Касса {$number}: не найдена точка.";
            $this->bump('cash_skipped');

            return;
        }

        $doc = CashDocument::create([
            'store_id' => $store->id,
            'client_id' => $client?->id,
            'operation_type_id' => $opType->id,
            'document_number' => $number !== '' ? $number : CashDocument::generateDocumentNumber($store->id, $direction),
            'external_id' => $ext,
            'document_date' => $at?->toDateString() ?? now()->toDateString(),
            'amount' => $amount,
            'comment' => mb_substr($comment, 0, 2000),
            'lmb_data' => ['source' => 'day_ops_json', 'вид' => $row['ВидОперации'] ?? null, 'статья' => $row['СтатьяДДС'] ?? null],
            'created_by' => null,
        ]);

        $this->postCashLedger($doc, $opType);
        $this->bump('cash_created');
    }

    private function importBank(array $row, bool $income): void
    {
        $number = trim((string) ($row['Номер'] ?? ''));
        $ext = '1c:bank:'.($income ? 'in' : 'out').':'.$number;
        if ($number !== '' && BankStatementLine::query()->where('external_id', $ext)->exists()) {
            $this->bump('bank_exists');

            return;
        }

        $org = is_array($row['СчетОрганизации'] ?? null) ? $row['СчетОрганизации'] : [];
        $accountNumber = trim((string) ($org['НомерСчета'] ?? ''));
        $bankName = trim((string) ($org['НаименованиеБанка'] ?? 'Банк 1С'));
        $bik = trim((string) ($org['БИК'] ?? ''));
        $corr = trim((string) ($org['КоррСчет'] ?? ''));

        $amount = $this->money($row['СуммаДокумента'] ?? null);
        if ($amount === null || $amount <= 0) {
            $this->bump('bank_skipped');

            return;
        }
        $signed = $income ? $amount : -1 * $amount;
        $at = $this->parseDate($row['Дата'] ?? $row['ДатаВходящегоДокумента'] ?? null);
        $cp = is_array($row['Контрагент'] ?? null) ? ($row['Контрагент']['Наименование'] ?? '') : '';
        $desc = trim(implode(' · ', array_filter([
            (string) ($row['ВидОперации'] ?? ''),
            (string) ($row['СтатьяДДС'] ?? ''),
            (string) ($row['НазначениеПлатежа'] ?? ''),
        ])));

        if ($this->dryRun) {
            $this->bump('bank_created');

            return;
        }

        $account = $this->resolveBankAccount($accountNumber, $bankName, $bik, $corr);
        $day = $at?->toDateString() ?? now()->toDateString();
        $statement = BankStatement::query()
            ->where('bank_account_id', $account->id)
            ->whereDate('date_from', $day)
            ->whereDate('date_to', $day)
            ->first();
        if (! $statement) {
            $statement = BankStatement::create([
                'bank_account_id' => $account->id,
                'date_from' => $day,
                'date_to' => $day,
                'notes' => 'Импорт опердня 1С',
                'created_by' => null,
            ]);
        }

        BankStatementLine::create([
            'bank_statement_id' => $statement->id,
            'line_date' => $day,
            'amount' => $signed,
            'description' => mb_substr($desc !== '' ? $desc : ($income ? 'Поступление' : 'Списание'), 0, 1000),
            'counterparty' => mb_substr((string) $cp, 0, 255) ?: null,
            'document_number' => $number !== '' ? $number : null,
            'external_id' => $ext,
        ]);
        $this->bump('bank_created');
    }

    private function postCashLedger(CashDocument $doc, CashOperationType $opType): void
    {
        try {
            $amount = (float) $doc->amount;
            $date = Carbon::parse($doc->document_date);
            $comment = $doc->document_number;
            if ($opType->isIncome()) {
                $this->ledger->post(
                    Account::CODE_CASH,
                    Account::CODE_SETTLEMENTS_OTHER,
                    $amount,
                    $date,
                    (int) $doc->store_id,
                    'cash_document',
                    $doc->id,
                    $comment,
                    $doc->client_id
                );
            } else {
                $debit = $opType->name === 'Оплата продавцу'
                    ? Account::CODE_SUPPLIERS
                    : Account::CODE_SETTLEMENTS_OTHER;
                $this->ledger->post(
                    $debit,
                    Account::CODE_CASH,
                    $amount,
                    $date,
                    (int) $doc->store_id,
                    'cash_document',
                    $doc->id,
                    $comment,
                    $doc->client_id
                );
            }
        } catch (\Throwable $e) {
            $this->warnings[] = 'Проводка кассы '.$doc->document_number.': '.$e->getMessage();
        }
    }

    /**
     * CRM-события 1С: личные встречи → колл-центр + client_visits,
     * ревизии → колл-центр. Телефонные звонки пропускаем (идут из MTS).
     *
     * @param  array<string, mixed>  $row
     */
    private function importCrmEvent(array $row): void
    {
        $kind = trim((string) ($row['ВидОперации'] ?? ''));
        if ($kind === 'Телефонный звонок') {
            $this->bump('skipped_phone_event');

            return;
        }
        if (! in_array($kind, ['Личная встреча', 'Ревизия'], true)) {
            $this->bump('skipped_other');

            return;
        }

        $number = trim((string) ($row['Номер'] ?? ''));
        $ext = '1c:crm-event:'.$number;
        if ($number !== '' && CallCenterContact::query()->where('external_id', $ext)->exists()) {
            $this->bump($kind === 'Ревизия' ? 'revision_exists' : 'visit_exists');

            return;
        }

        $at = $this->parseDate($row['НачалоСобытия'] ?? $row['Дата'] ?? null);
        $store = $this->resolveStore((string) ($row['Филиал'] ?? ''));
        $client = $this->resolveContragent($row['Контрагент'] ?? null);
        $goal = trim((string) ($row['Цель'] ?? ''));
        $result = trim((string) ($row['Результат'] ?? ''));
        $source = trim((string) ($row['ИсточникРекламы'] ?? ''));
        $basis = trim((string) ($row['ДокументОснования'] ?? ''));
        $responsible = trim((string) ($row['Ответственный'] ?? ''));
        $direction = match (trim((string) ($row['ТипСобытия'] ?? ''))) {
            'Исходящее' => 'outgoing',
            default => 'incoming',
        };

        $notesParts = array_filter([
            $kind === 'Ревизия' ? 'Ревизия 1С' : null,
            $goal !== '' ? 'Цель: '.$goal : null,
            $result !== '' ? 'Результат: '.$result : null,
            $source !== '' ? 'Источник: '.$source : null,
            $basis !== '' ? 'Основание: '.$basis : null,
            $responsible !== '' ? 'Ответственный: '.$responsible : null,
            $number !== '' ? '№'.$number : null,
        ]);
        $notes = $this->shortField(implode(' · ', $notesParts), 2000);

        $contactName = null;
        if (is_array($row['Контрагент'] ?? null)) {
            $contactName = trim((string) (($row['Контрагент']['НаименованиеПолное'] ?? '')
                ?: ($row['Контрагент']['Наименование'] ?? '')));
        }

        $channel = $kind === 'Ревизия' ? 'other' : 'visit';
        $outcome = $this->mapCrmEventOutcome($result, $goal);

        if ($this->dryRun) {
            $this->bump($kind === 'Ревизия' ? 'revision_created' : 'visit_created');

            return;
        }

        CallCenterContact::create([
            'external_id' => $ext,
            'client_id' => $client?->id,
            'channel' => $channel,
            'direction' => $direction,
            'store_id' => $store?->id,
            'contact_date' => $at ?? now(),
            'contact_name' => $this->shortField($contactName, 255),
            'contact_phone' => $client?->phone,
            'notes' => $notes,
            'outcome' => $outcome,
            'created_by' => null,
        ]);

        if ($kind === 'Личная встреча' && $store && $client) {
            ClientVisit::create([
                'store_id' => $store->id,
                'client_id' => $client->id,
                'visit_purpose' => $this->mapVisitPurpose($goal, $result),
                'visited_at' => $at ?? now(),
                'created_by' => null,
            ]);
            $this->bump('client_visit_created');
        }

        $this->bump($kind === 'Ревизия' ? 'revision_created' : 'visit_created');
    }

    private function mapCrmEventOutcome(string $result, string $goal): ?string
    {
        $text = mb_strtolower($result.' '.$goal);
        if (str_contains($text, 'залог')) {
            return 'converted_pawn';
        }
        if (str_contains($text, 'скупк')) {
            return 'converted_purchase';
        }
        if (str_contains($text, 'комисс')) {
            return 'converted_commission';
        }
        if ($result !== '') {
            return 'closed';
        }

        return 'new';
    }

    private function mapVisitPurpose(string $goal, string $result): string
    {
        $text = mb_strtolower($goal.' '.$result);
        if (str_contains($text, 'выкуп')) {
            return ClientVisit::PURPOSE_REDEMPTION;
        }
        if (str_contains($text, 'оценк') || str_contains($text, 'залог')) {
            return ClientVisit::PURPOSE_APPRAISAL;
        }
        if (str_contains($text, 'идентификац')) {
            return ClientVisit::PURPOSE_IDENTIFICATION;
        }

        return ClientVisit::PURPOSE_NON_TARGET;
    }

    private function importProductEvent(array $row): void
    {
        $number = trim((string) ($row['Номер'] ?? ''));
        $ext = '1c:product-event:'.$number;
        $existing = ($number !== '')
            ? LmbProductEvent::query()->where('external_id', $ext)->first()
            : null;
        if ($existing && ! $this->refreshProductEvents) {
            $this->bump('product_event_exists');

            return;
        }

        $kind = trim((string) ($row['ВидОперации'] ?? ''));
        $eventType = match ($kind) {
            'Перемещение' => LmbProductEvent::TYPE_MOVE,
            'К перемещению' => LmbProductEvent::TYPE_MOVE_PENDING,
            'Смена статуса (залог/товар)' => LmbProductEvent::TYPE_STATUS,
            default => LmbProductEvent::TYPE_OTHER,
        };

        $at = $this->parseDate($row['Дата'] ?? null);
        $fromName = trim((string) (($row['НачальныйОтправитель'] ?? '') !== ''
            ? $row['НачальныйОтправитель']
            : ($row['Филиал'] ?? '')));
        $fromStore = $fromName !== '' ? $this->resolveStore($fromName) : null;

        $toName = $kind === 'К перемещению'
            ? trim((string) ($row['ФилиалПолучатель'] ?? ''))
            : trim((string) (($row['ПолучательКонечный'] ?? '') !== ''
                ? $row['ПолучательКонечный']
                : ($row['ФилиалПолучатель'] ?? '')));
        $toStore = $toName !== '' ? $this->resolveStore($toName) : null;

        $statusName = trim((string) ($row['СтатусТовара'] ?? ''));
        $status = $statusName !== '' ? $this->resolveItemStatusFrom1c($statusName) : null;

        $item = $this->resolveItemFromProductEvent($row, $fromStore ?? $toStore);

        $sourceRef = trim((string) ($row['ДокументПоступленияЗалог'] ?? ''));
        if ($sourceRef === '') {
            $sales = $row['ДокументыПродажи'] ?? null;
            if (is_array($sales) && isset($sales[0]) && is_array($sales[0])) {
                $sourceRef = trim((string) ($sales[0]['Документ'] ?? ''));
            }
        }
        if ($sourceRef === '') {
            $sourceRef = trim((string) ($row['Комплектация'] ?? $row['ДокументВозврата'] ?? ''));
        }

        $qtyRaw = trim((string) ($row['Количество'] ?? ''));
        $quantity = is_numeric($qtyRaw) ? (int) $qtyRaw : null;

        $isActionable = in_array($eventType, [
            LmbProductEvent::TYPE_MOVE,
            LmbProductEvent::TYPE_MOVE_PENDING,
            LmbProductEvent::TYPE_STATUS,
        ], true);

        if ($this->dryRun) {
            $this->bump('product_event_created');
            if ($isActionable) {
                $this->bump($item ? 'product_event_will_apply' : 'product_event_unmatched');
            }

            return;
        }

        $applied = false;
        if ($item && in_array($eventType, [LmbProductEvent::TYPE_MOVE, LmbProductEvent::TYPE_MOVE_PENDING], true) && $toStore) {
            if ((int) $item->store_id !== (int) $toStore->id) {
                $item->update(['store_id' => $toStore->id]);
            }
            $applied = true;
        }

        if ($item && $status && $eventType === LmbProductEvent::TYPE_STATUS) {
            if ((int) $item->status_id !== (int) $status->id) {
                $oldStatusId = $item->status_id;
                $item->update(['status_id' => $status->id]);
                $history = new ItemStatusHistory([
                    'item_id' => $item->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $status->id,
                    'changed_by' => null,
                ]);
                $history->created_at = $at ?? now();
                $history->save();
            }
            $applied = true;
        }

        $attrs = [
            'external_id' => $ext,
            'event_type' => $eventType === LmbProductEvent::TYPE_OTHER
                ? mb_substr($kind !== '' ? $kind : 'other', 0, 64)
                : $eventType,
            'event_number' => $number !== '' ? $number : null,
            'event_at' => $at,
            'item_id' => $item?->id,
            'from_store_id' => $fromStore?->id,
            'to_store_id' => $toStore?->id,
            'status_name' => $statusName !== '' ? $statusName : null,
            'status_id' => $status?->id,
            'responsible' => $this->shortField((string) ($row['Ответственный'] ?? ''), 255),
            'executor' => $this->shortField((string) ($row['Исполнитель'] ?? ''), 255),
            'quantity' => $quantity,
            'description' => $this->shortField(trim(implode(' · ', array_filter([
                (string) ($row['Описание'] ?? ''),
                (string) ($row['Комментарий'] ?? ''),
                $kind,
            ]))), 2000),
            'source_doc_ref' => $this->shortField($sourceRef, 255),
            'applied' => $applied,
            'payload' => $this->slimRow($row),
        ];

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();
            $this->bump('product_event_refreshed');
        } else {
            LmbProductEvent::create($attrs);
            $this->bump('product_event_created');
        }

        if ($isActionable) {
            $this->bump($applied ? 'product_event_applied' : 'product_event_unmatched');
        }
    }

    /** @param  array<string, mixed>  $row */
    private function resolveItemFromProductEvent(array $row, ?Store $store): ?Item
    {
        $nom = $this->firstNomenclature($row['ТоварыОценки'] ?? null)
            ?? $this->firstNomenclature($row['ТабличнаяЧасть'] ?? null);
        if ($nom) {
            $item = $this->resolveItem($nom, $store);
            if ($item && $item->exists) {
                return $item;
            }
        }

        $pawnRef = trim((string) ($row['ДокументПоступленияЗалог'] ?? ''));
        if ($pawnRef !== '') {
            $docNumber = $this->extractDocNumber($pawnRef);
            if ($docNumber !== null) {
                $pawn = $this->findPawn('', $docNumber);
                if ($pawn?->item_id) {
                    return Item::query()->find($pawn->item_id);
                }
            }
        }

        $purchaseRef = trim((string) ($row['ДокументПоступленияСкупка'] ?? ''));
        if ($purchaseRef !== '') {
            $docNumber = $this->extractDocNumber($purchaseRef);
            if ($docNumber !== null) {
                $purchase = PurchaseContract::query()
                    ->where('contract_number', $docNumber)
                    ->orWhere('lmb_doc_uid', '1c:purchase:'.$docNumber)
                    ->first();
                if ($purchase?->item_id) {
                    return Item::query()->find($purchase->item_id);
                }
            }
        }

        $sales = $row['ДокументыПродажи'] ?? null;
        if (is_array($sales)) {
            foreach ($sales as $saleDoc) {
                if (! is_array($saleDoc)) {
                    continue;
                }
                $label = (string) ($saleDoc['Документ'] ?? '');
                if (! str_contains(mb_strtolower($label), 'реализация')) {
                    continue;
                }
                $docNumber = $this->extractDocNumber($label);
                if ($docNumber === null) {
                    continue;
                }
                $sale = SaleContract::query()
                    ->where('contract_number', $docNumber)
                    ->orWhere('external_id', '1c:sale:'.$docNumber)
                    ->first();
                if ($sale?->item_id) {
                    return Item::query()->find($sale->item_id);
                }
            }
        }

        $returnRef = trim((string) ($row['ДокументВозврата'] ?? ''));
        if ($returnRef !== '') {
            $docNumber = $this->extractDocNumber($returnRef);
            if ($docNumber !== null) {
                $sale = SaleContract::query()
                    ->where('contract_number', $docNumber)
                    ->orWhere('external_id', '1c:sale:'.$docNumber)
                    ->first();
                if ($sale?->item_id) {
                    return Item::query()->find($sale->item_id);
                }
            }
        }

        // Скупка в JSON часто без номера документа — ищем договор по клиенту и дате.
        $at = $this->parseDate($row['Дата'] ?? null);
        $client = $this->resolveContragent($row['Контрагент'] ?? null);
        if ($client && $at) {
            $purchase = PurchaseContract::query()
                ->where('client_id', $client->id)
                ->whereDate('purchase_date', $at->toDateString())
                ->whereNotNull('item_id')
                ->orderByDesc('id')
                ->first();
            if ($purchase?->item_id) {
                return Item::query()->find($purchase->item_id);
            }
        }

        return null;
    }

    /**
     * Повторная привязка уже сохранённых событий (item_id / склад / статус)
     * по payload из 1С. Нужно после догрузки договоров или правок резолвера.
     *
     * @return array<string, int>
     */
    public function relinkExisting(?string $onlyType = null): array
    {
        $this->stats = [];
        $q = LmbProductEvent::query()->orderBy('id');
        if ($onlyType !== null && $onlyType !== '') {
            $q->where('event_type', $onlyType);
        }

        foreach ($q->cursor() as $event) {
            $row = is_array($event->payload) ? $event->payload : [];
            if ($row === []) {
                $this->bump('relink_skipped_empty');

                continue;
            }

            $kind = trim((string) ($row['ВидОперации'] ?? $event->event_type));
            $eventType = match ($kind) {
                'Перемещение' => LmbProductEvent::TYPE_MOVE,
                'К перемещению' => LmbProductEvent::TYPE_MOVE_PENDING,
                'Смена статуса (залог/товар)' => LmbProductEvent::TYPE_STATUS,
                default => $event->event_type,
            };

            $fromName = trim((string) (($row['НачальныйОтправитель'] ?? '') !== ''
                ? $row['НачальныйОтправитель']
                : ($row['Филиал'] ?? '')));
            $fromStore = $fromName !== '' ? $this->resolveStore($fromName) : null;

            $toName = $kind === 'К перемещению'
                ? trim((string) ($row['ФилиалПолучатель'] ?? ''))
                : trim((string) (($row['ПолучательКонечный'] ?? '') !== ''
                    ? $row['ПолучательКонечный']
                    : ($row['ФилиалПолучатель'] ?? '')));
            $toStore = $toName !== '' ? $this->resolveStore($toName) : null;

            $statusName = trim((string) ($row['СтатусТовара'] ?? $event->status_name ?? ''));
            $status = $statusName !== '' ? $this->resolveItemStatusFrom1c($statusName) : null;

            $item = $event->item_id
                ? Item::query()->find($event->item_id)
                : $this->resolveItemFromProductEvent($row, $fromStore ?? $toStore);

            $applied = (bool) $event->applied;
            if ($item && in_array($eventType, [LmbProductEvent::TYPE_MOVE, LmbProductEvent::TYPE_MOVE_PENDING], true) && $toStore) {
                if ((int) $item->store_id !== (int) $toStore->id) {
                    $item->update(['store_id' => $toStore->id]);
                }
                $applied = true;
            }

            if ($item && $status && $eventType === LmbProductEvent::TYPE_STATUS) {
                if ((int) $item->status_id !== (int) $status->id) {
                    $oldStatusId = $item->status_id;
                    $item->update(['status_id' => $status->id]);
                    $history = new ItemStatusHistory([
                        'item_id' => $item->id,
                        'old_status_id' => $oldStatusId,
                        'new_status_id' => $status->id,
                        'changed_by' => null,
                    ]);
                    $history->created_at = $event->event_at ?? now();
                    $history->save();
                }
                $applied = true;
            }

            $changed = false;
            if ($item && (int) $event->item_id !== (int) $item->id) {
                $event->item_id = $item->id;
                $changed = true;
                $this->bump('relink_item_linked');
            }
            if ($fromStore && (int) $event->from_store_id !== (int) $fromStore->id) {
                $event->from_store_id = $fromStore->id;
                $changed = true;
            }
            if ($toStore && (int) $event->to_store_id !== (int) $toStore->id) {
                $event->to_store_id = $toStore->id;
                $changed = true;
            }
            if ($status && (int) $event->status_id !== (int) $status->id) {
                $event->status_id = $status->id;
                $changed = true;
            }
            if ($applied !== (bool) $event->applied) {
                $event->applied = $applied;
                $changed = true;
                $this->bump($applied ? 'relink_applied' : 'relink_unapplied');
            }

            if ($changed) {
                $event->save();
                $this->bump('relink_updated');
            } else {
                $this->bump('relink_unchanged');
            }
        }

        return $this->stats;
    }

    private function extractDocNumber(string $label): ?string
    {
        if (preg_match('/(\d{2}БП-\d+)/u', $label, $m)) {
            return $m[1];
        }

        return null;
    }

    private function resolveItemStatusFrom1c(string $name): ItemStatus
    {
        $map = [
            'Залог' => 'Принят в ломбард',
            'Товар' => 'На витрине',
        ];
        $portalName = $map[$name] ?? $name;
        $key = mb_strtolower($portalName);
        if (isset($this->statusCache[$key])) {
            return $this->statusCache[$key];
        }

        $color = match ($name) {
            'Залог' => '#17a2b8',
            'Товар' => '#28a745',
            default => '#6c757d',
        };

        $status = ItemStatus::query()->firstOrCreate(
            ['name' => $portalName],
            ['color' => $color]
        );

        return $this->statusCache[$key] = $status;
    }

    private function findPawn(string $ticket, string $number): ?PawnContract
    {
        $q = PawnContract::query();
        if ($ticket !== '') {
            $found = (clone $q)->where('contract_number', $ticket)->first();
            if ($found) {
                return $found;
            }
            $found = (clone $q)->where('lmb_data->ticket', $ticket)->first();
            if ($found) {
                return $found;
            }
        }
        if ($number !== '') {
            $found = (clone $q)->where('contract_number', $number)->first();
            if ($found) {
                return $found;
            }
            $found = (clone $q)->where('lmb_doc_uid', 'like', '%'.$number.'%')->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /** @param  mixed  $nomBlock */
    private function firstNomenclature(mixed $nomBlock): ?array
    {
        if (! is_array($nomBlock) || $nomBlock === []) {
            return null;
        }
        $first = $nomBlock[0] ?? null;
        if (! is_array($first)) {
            return null;
        }
        $nom = $first['Номенклатура'] ?? $first;
        if (! is_array($nom)) {
            return null;
        }
        // preserve price fields from line
        foreach (['Цена', 'Сумма', 'Количество'] as $k) {
            if (isset($first[$k]) && ! isset($nom[$k])) {
                $nom[$k] = $first[$k];
            }
        }

        return $nom;
    }

    private function resolveItem(array $nom, ?Store $store): ?Item
    {
        $code = trim((string) ($nom['Код'] ?? ''));
        $name = trim((string) ($nom['Наименование'] ?? 'Товар 1С'));
        if ($code !== '') {
            $existing = Item::query()->where('lmb_ref', $code)->orWhere('barcode', $code)->first();
            if ($existing) {
                return $existing;
            }
        }
        if ($this->dryRun) {
            return new Item(['name' => $name]);
        }
        $storeId = $store?->id ?? $this->defaultStoreId();
        $statusId = ItemStatus::query()->orderBy('id')->value('id');
        $price = $this->money($nom['Ссуда'] ?? $nom['ЦенаРеализации'] ?? $nom['Цена'] ?? null);

        return Item::create([
            'name' => mb_substr($name, 0, 255),
            'description' => trim((string) ($nom['Описание'] ?? '')),
            'metal' => $this->shortField($this->guessMetal($nom), 32),
            'sample' => $this->shortField($this->guessSample($nom), 16),
            'weight_grams' => $this->money($nom['Вес'] ?? $nom['ЧистыйВес'] ?? null),
            'store_id' => $storeId,
            'status_id' => $statusId,
            'barcode' => $code !== '' ? $code : Item::generateBarcode(),
            'lmb_ref' => $code !== '' ? $code : null,
            'initial_price' => $price,
            'current_price' => $price,
        ]);
    }

    private function guessMetal(array $nom): ?string
    {
        $type = trim((string) ($nom['ТипВещи'] ?? ''));
        $name = (string) ($nom['Наименование'] ?? '');
        if (preg_match('/золот/ui', $name.$type)) {
            return 'Золото';
        }
        if (preg_match('/серебр/ui', $name.$type)) {
            return 'Серебро';
        }
        $metal = trim((string) ($nom['Металл'] ?? ''));
        // В выгрузке 1С в «Металл» часто лежит вес — игнорируем числовые значения.
        if ($metal !== '' && ! is_numeric(str_replace([' ', ','], ['', '.'], $metal))) {
            return $metal;
        }

        return $type !== '' ? $type : null;
    }

    private function guessSample(array $nom): ?string
    {
        $proba = trim((string) ($nom['Проба'] ?? ''));
        if ($proba === '') {
            return null;
        }
        if (preg_match('/(\d{3,4})/u', $proba, $m)) {
            return $m[1];
        }

        return $proba;
    }

    private function shortField(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $max);
    }

    private function resolveContragent(mixed $c): ?Client
    {
        if (! is_array($c)) {
            return null;
        }
        $code = trim((string) ($c['Код'] ?? ''));
        $name = trim((string) ($c['НаименованиеПолное'] ?? $c['Наименование'] ?? ''));
        $phone = trim((string) ($c['Телефона1'] ?? ''));
        $cacheKey = $code !== '' ? 'c:'.$code : 'n:'.mb_strtolower($name);
        if (isset($this->clientCache[$cacheKey])) {
            return $this->clientCache[$cacheKey];
        }

        $client = null;
        if ($code !== '') {
            $client = Client::query()->where('user_uid', $code)->first();
        }
        if (! $client && $phone !== '') {
            $key = Client::phoneToKey($phone);
            if ($key) {
                $client = Client::query()->where('phone_key', $key)->first();
            }
        }
        if (! $client && $name !== '') {
            $client = Client::query()->where('full_name', $name)->first();
        }

        if ($client) {
            return $this->clientCache[$cacheKey] = $client;
        }
        if ($this->dryRun) {
            return $this->clientCache[$cacheKey] = new Client(['full_name' => $name, 'user_uid' => $code]);
        }
        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $client = Client::create([
            'full_name' => $name,
            'last_name' => $parts[0] ?? null,
            'first_name' => $parts[1] ?? null,
            'patronymic' => $parts[2] ?? null,
            'phone' => $phone !== '' ? preg_replace('/\s+/', '', $phone) : null,
            'phone_key' => Client::phoneToKey($phone),
            'user_uid' => $code !== '' ? $code : null,
            'passport_data' => $this->passportString($c),
            'lmb_full_name' => $name,
            'lmb_data' => ['source' => 'day_ops_json', 'code' => $code],
            'client_type' => 'individual',
        ]);

        return $this->clientCache[$cacheKey] = $client;
    }

    private function ensureAnonymousBuyer(): Client
    {
        $name = 'Покупатель (1С)';
        $existing = Client::query()->where('full_name', $name)->first();
        if ($existing) {
            return $existing;
        }
        if ($this->dryRun) {
            return new Client(['full_name' => $name]);
        }

        return Client::create([
            'full_name' => $name,
            'client_type' => 'individual',
            'notes' => 'Служебный контрагент для импорта реализаций 1С',
        ]);
    }

    private function resolveStore(string $name): ?Store
    {
        $name = trim($name);
        if ($name === '' || strcasecmp($name, 'None') === 0) {
            return Store::query()->find($this->defaultStoreId());
        }
        $normalized = $this->normalizeStoreName($name);
        $key = mb_strtolower($normalized);
        if (isset($this->storeCache[$key])) {
            return $this->storeCache[$key];
        }

        $store = Store::query()->whereRaw('LOWER(name) = ?', [$key])->first();
        if (! $store) {
            $store = Store::query()->where('name', 'like', '%'.$normalized.'%')->first();
        }
        if (! $store && ! $this->dryRun) {
            $store = Store::create([
                'name' => $normalized,
                'is_active' => true,
            ]);
            $this->bump('stores_created');
        }

        if ($store) {
            $this->storeCache[$key] = $store;
        }

        return $store;
    }

    private function normalizeStoreName(string $name): string
    {
        $name = trim($name);
        $lower = mb_strtolower($name);
        if (str_contains($lower, 'витрина')) {
            if (str_contains($lower, 'горский')) {
                return 'Горский, 1';
            }
            if (str_contains($lower, 'колхидская')) {
                return 'Колхидская, 11';
            }
            if (str_contains($lower, 'титова')) {
                return 'Титова 30';
            }
            if (str_contains($lower, 'станиславского')) {
                return 'Станиславского, 29';
            }
            if (str_contains($lower, 'мичурина')) {
                return 'Мичурина, 23/1';
            }
        }
        // Сейф — отдельная точка хранения, не витрина «Горский, 1».
        if (str_contains($lower, 'сейф') && str_contains($lower, 'горский')) {
            return 'Горский, сейф';
        }
        if (str_starts_with($lower, 'комиссионка')) {
            return trim((string) preg_replace('/^комиссионка\s+/ui', '', $name));
        }

        return $name;
    }

    private function resolveBankAccount(string $accountNumber, string $bankName, string $bik, string $corr): BankAccount
    {
        if ($accountNumber !== '') {
            $existing = BankAccount::query()->where('account_number', $accountNumber)->first();
            if ($existing) {
                return $existing;
            }
        }
        $name = $bankName !== '' ? $bankName : 'Счёт 1С';
        if ($accountNumber !== '') {
            $name .= ' · '.substr($accountNumber, -4);
        }

        return BankAccount::create([
            'name' => mb_substr($name, 0, 255),
            'bank_name' => $bankName !== '' ? $bankName : null,
            'account_number' => $accountNumber !== '' ? $accountNumber : ('1C-'.Str::random(8)),
            'bik' => $bik !== '' ? $bik : null,
            'correspondent_account' => $corr !== '' ? $corr : null,
            'is_active' => true,
            'sort_order' => 100,
        ]);
    }

    private function mapCashOperationName(string $kind, string $direction): string
    {
        $map = [
            'Возврат займа контрагентом' => 'Возврат займа',
            'Выдача займа контрагенту' => 'Выдача займа',
            'Оплата от покупателя' => 'Оплата от покупателя',
            'Возврат покупателю' => 'Возврат покупателю',
            'Прочий расход' => 'Прочий расход',
            'Оплата продавцу' => 'Оплата продавцу',
        ];
        if (isset($map[$kind])) {
            return $map[$kind];
        }

        return $direction === 'income' ? 'Прочий приход' : 'Прочий расход';
    }

    private function ensureCashOperationTypes(): void
    {
        $defs = [
            ['Выдача займа', 'expense', 10],
            ['Возврат займа', 'income', 20],
            ['Оплата от покупателя', 'income', 30],
            ['Возврат покупателю', 'expense', 40],
            ['Оплата продавцу', 'expense', 50],
            ['Прочий расход', 'expense', 60],
            ['Прочий приход', 'income', 70],
            ['Займ от учредителя', 'income', 80],
            ['Выдача заработной платы', 'expense', 90],
        ];
        foreach ($defs as [$name, $dir, $sort]) {
            CashOperationType::query()->firstOrCreate(
                ['name' => $name],
                ['direction' => $dir, 'sort_order' => $sort, 'is_active' => true]
            );
        }
    }

    private function cashType(string $name, string $direction): CashOperationType
    {
        $key = $name.'|'.$direction;
        if (isset($this->cashTypeCache[$key])) {
            return $this->cashTypeCache[$key];
        }
        $type = CashOperationType::query()->firstOrCreate(
            ['name' => $name],
            ['direction' => $direction, 'sort_order' => 100, 'is_active' => true]
        );

        return $this->cashTypeCache[$key] = $type;
    }

    private function defaultStoreId(): int
    {
        if ($this->defaultStoreId) {
            return $this->defaultStoreId;
        }
        $id = (int) (Store::query()->where('is_active', true)->orderBy('id')->value('id')
            ?? Store::query()->orderBy('id')->value('id'));
        if (! $id && ! $this->dryRun) {
            $id = (int) Store::create(['name' => 'Основная точка', 'is_active' => true])->id;
        }
        $this->defaultStoreId = $id ?: 1;

        return $this->defaultStoreId;
    }

    private function money(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        $s = str_replace(["\xc2\xa0", ' '], '', (string) $v);
        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }

    private function percentFromScheme(mixed $scheme): ?float
    {
        if (! is_array($scheme)) {
            return null;
        }
        $v = $this->money($scheme['ЗаКредит'] ?? null);

        return $v;
    }

    private function parseDate(mixed $v): ?Carbon
    {
        if ($v === null || $v === '' || str_starts_with((string) $v, '0001-')) {
            return null;
        }
        try {
            return Carbon::parse((string) $v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function passportString(array $c): ?string
    {
        $docs = $c['ДокументыФизическихЛиц'] ?? null;
        if (! is_array($docs) || $docs === []) {
            return null;
        }
        $d = $docs[0];
        if (! is_array($d)) {
            return null;
        }

        return trim((string) ($d['Представление'] ?? (
            trim(($d['Серия'] ?? '').' '.($d['Номер'] ?? ''))
        ))) ?: null;
    }

    /** @param  array<string, mixed>  $row */
    private function slimRow(array $row): array
    {
        $copy = $row;
        unset(
            $copy['Залогодатель'],
            $copy['ТабличнаяЧасть'],
            $copy['КассовыеОрдера'],
            $copy['РасшифровкаПлатежа'],
            $copy['ФотоДокументов'],
            $copy['РекламныеПлощадки'],
            $copy['КонтактнаяИнформация'],
            $copy['КонтактныеДанные'],
        );

        // Компактный контрагент — нужен для повторной привязки событий.
        if (isset($copy['Контрагент']) && is_array($copy['Контрагент'])) {
            $c = $copy['Контрагент'];
            $copy['Контрагент'] = array_filter([
                'Код' => $c['Код'] ?? null,
                'Наименование' => $c['Наименование'] ?? null,
                'НаименованиеПолное' => $c['НаименованиеПолное'] ?? null,
                'Телефона1' => $c['Телефона1'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
            if ($copy['Контрагент'] === []) {
                unset($copy['Контрагент']);
            }
        }

        // Компактная номенклатура оценки (без тяжёлых вложений).
        if (isset($copy['ТоварыОценки']) && is_array($copy['ТоварыОценки'])) {
            $slimNom = [];
            foreach (array_slice($copy['ТоварыОценки'], 0, 3) as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $nom = is_array($line['Номенклатура'] ?? null) ? $line['Номенклатура'] : $line;
                $slimNom[] = array_filter([
                    'Код' => $nom['Код'] ?? null,
                    'Наименование' => $nom['Наименование'] ?? null,
                    'Цена' => $line['Цена'] ?? $nom['Цена'] ?? null,
                    'Ссуда' => $nom['Ссуда'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '');
            }
            if ($slimNom === []) {
                unset($copy['ТоварыОценки']);
            } else {
                $copy['ТоварыОценки'] = $slimNom;
            }
        }

        return $copy;
    }

    private function bump(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }

    private function reset(): void
    {
        $this->stats = [];
        $this->errors = [];
        $this->warnings = [];
        $this->storeCache = [];
        $this->clientCache = [];
        $this->cashTypeCache = [];
        $this->statusCache = [];
        $this->defaultStoreId = null;
    }

    /** @return array{ok: bool, stats: array<string, int>, errors: list<string>, warnings: list<string>, total: int} */
    private function result(int $total): array
    {
        return [
            'ok' => $this->errors === [],
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => array_slice($this->warnings, 0, 50),
            'total' => $total,
        ];
    }
}
