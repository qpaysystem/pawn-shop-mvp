<?php

namespace App\Services\Mxl;

/** Ключи сопоставления позиций инвентарки 1С с остатками портала. */
class InventoryPositionMatcher
{
    public function __construct(
        private ReceiptsMxlImportService $stores = new ReceiptsMxlImportService,
    ) {}

    /** @param array<string, mixed> $row */
    public function matchKeysForInventoryRow(array $row): array
    {
        $store = (string) ($row['store'] ?? '');
        $doc = (string) ($row['document'] ?? '');
        $item = (string) ($row['item'] ?? '');
        $amount = (float) ($row['amount'] ?? 0);
        $client = (string) ($row['client'] ?? '');

        return array_values(array_unique([
            $this->positionKey($store, $doc, $item, $amount),
            $this->positionKeyLoose($store, $item, $amount),
            $this->positionKeyWithClient($store, $item, $amount, $client),
            $this->positionKeyClientItem($item, $amount, $client),
        ]));
    }

    /** @param array<string, mixed> $portal */
    public function matchKeysForPortalRow(array $portal): array
    {
        return $this->matchKeysForInventoryRow([
            'store' => $portal['store'] ?? '',
            'document' => $portal['contract_number'] ?? '',
            'item' => $portal['item'] ?? '',
            'amount' => $portal['amount'] ?? 0,
            'client' => $portal['client'] ?? '',
        ]);
    }

    public function positionKey(string $store, string $document, string $item, float $amount): string
    {
        return implode('|', [
            $this->normStore($store),
            mb_strtolower(trim($document)),
            $this->normItem($item),
            (string) (int) round($amount),
        ]);
    }

    public function positionKeyLoose(string $store, string $item, float $amount): string
    {
        return implode('|', [
            $this->normStore($store),
            $this->normItem($item),
            (string) (int) round($amount),
        ]);
    }

    public function positionKeyWithClient(string $store, string $item, float $amount, string $client): string
    {
        return implode('|', [
            $this->normStore($store),
            $this->normItem($item),
            (string) (int) round($amount),
            $this->normClient($client),
        ]);
    }

    public function positionKeyClientItem(string $item, float $amount, string $client): string
    {
        return implode('|', [
            $this->normItem($item),
            (string) (int) round($amount),
            $this->normClient($client),
        ]);
    }

  /** @param array<string, mixed> $portal @param array<string, mixed> $inventory */
    public function scoreMatch(array $portal, array $inventory): int
    {
        $score = 0;
        $portalDoc = mb_strtolower(trim((string) ($portal['contract_number'] ?? '')));
        $invDoc = mb_strtolower(trim((string) ($inventory['document'] ?? '')));
        $portalType = (string) ($portal['kind'] ?? '');
        $invType = (string) ($inventory['type'] ?? '');

        if ($portalDoc !== '' && $portalDoc === $invDoc) {
            $score += 10_000;
        }

        if ($portalType === $invType) {
            $score += 1_000;
        }

        if ($this->normClient((string) ($portal['client'] ?? '')) === $this->normClient((string) ($inventory['client'] ?? ''))
            && $this->normClient((string) ($inventory['client'] ?? '')) !== '') {
            $score += 100;
        }

        if ($portalType === 'pawn' && str_starts_with($portalDoc, 'елз-')) {
            $score += 50;
        }

        if ($portalType === 'purchase' && str_starts_with($portalDoc, '00бп-')) {
            $score += 50;
        }

        if ($this->positionKeyLoose(
            (string) ($portal['store'] ?? ''),
            (string) ($portal['item'] ?? ''),
            (float) ($portal['amount'] ?? 0),
        ) === $this->positionKeyLoose(
            (string) ($inventory['store'] ?? ''),
            (string) ($inventory['item'] ?? ''),
            (float) ($inventory['amount'] ?? 0),
        )) {
            $score += 10;
        }

        return $score;
    }

    public function normStore(string $store): string
    {
        return mb_strtolower($this->stores->normalizeStoreName($store));
    }

    public function normItem(string $item): string
    {
        $item = mb_strtolower(trim($item));
        $item = preg_replace('/\s+/u', ' ', $item) ?? '';

        return $item;
    }

    public function normClient(string $client): string
    {
        $client = mb_strtolower(trim($client));
        $client = preg_replace('/\s+/u', ' ', $client) ?? '';

        return $client;
    }
}
