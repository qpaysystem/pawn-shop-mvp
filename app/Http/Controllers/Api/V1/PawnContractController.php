<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\User;
use App\Services\PawnContractCreationService;
use App\Services\PawnContractRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Mobile v1: список, создание и карточка залогов. */
class PawnContractController extends Controller
{
    public function __construct(
        private readonly PawnContractCreationService $creationService,
        private readonly PawnContractRedemptionService $redemptionService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payload = $request->has('payload')
            ? json_decode((string) $request->input('payload'), true)
            : $request->all();

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload'], 422);
        }

        try {
            $validated = validator($payload, [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
                'visit_purpose' => ['nullable', 'in:appraisal,redemption,non_target,identification'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
                'client' => ['nullable', 'array'],
                'client.last_name' => ['required_without:client_id', 'string', 'max:100'],
                'client.first_name' => ['required_without:client_id', 'string', 'max:100'],
                'client.patronymic' => ['nullable', 'string', 'max:100'],
                'client.phone' => ['required_without:client_id', 'string', 'max:50'],
                'client.passport_data' => ['nullable', 'string', 'max:500'],
                'item' => ['required', 'array'],
                'item.name' => ['required', 'string', 'max:255'],
                'item.description' => ['nullable', 'string', 'max:2000'],
                'item.category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
                'item.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
                'item.status_id' => ['required', 'integer', 'exists:item_statuses,id'],
                'item.storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
                'item.initial_price' => ['nullable', 'numeric', 'min:0'],
                'item.current_price' => ['nullable', 'numeric', 'min:0'],
                'loan' => ['required', 'array'],
                'loan.loan_amount' => ['required', 'numeric', 'min:0.01'],
                'loan.loan_percent' => ['nullable', 'numeric', 'min:0'],
                'loan.loan_date' => ['required', 'date'],
                'loan.expiry_date' => ['required', 'date', 'after_or_equal:loan.loan_date'],
            ])->validate();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        if (empty($validated['client_id']) && empty($validated['client'])) {
            return response()->json(['message' => 'client_id or client required'], 422);
        }

        $photoFiles = $request->file('photos', []);
        if ($photoFiles && ! is_array($photoFiles)) {
            $photoFiles = [$photoFiles];
        }

        try {
            $contract = $this->creationService->create($user, $validated, $photoFiles ?? []);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($this->contractPayload($contract, true), 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = PawnContract::query()
            ->with(['client:id,full_name,phone,last_name,first_name,patronymic', 'item:id,name,photos,store_id,status_id'])
            ->whereIn('store_id', $user->allowedStoreIds());

        if ($request->filled('store_id')) {
            $storeId = (int) $request->store_id;
            if (in_array($storeId, $user->allowedStoreIds(), true)) {
                $query->where('store_id', $storeId);
            }
        }

        $status = $request->get('status');
        if ($status === 'redeemed') {
            $query->where('is_redeemed', true);
        } elseif ($status === 'active') {
            $query->where('is_redeemed', false)
                ->where(function ($q) {
                    $q->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                });
        } elseif ($status === 'overdue') {
            $query->where('is_redeemed', false)
                ->whereDate('expiry_date', '<', now()->toDateString());
        }

        if ($request->filled('q')) {
            $q = (string) $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('contract_number', 'like', "%{$q}%")
                    ->orWhereHas('client', function ($clientQuery) use ($q) {
                        $clientQuery->where('full_name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    })
                    ->orWhereHas('item', function ($itemQuery) use ($q) {
                        $itemQuery->where('name', 'like', "%{$q}%");
                    });
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (PawnContract $c) => $this->contractPayload($c))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, PawnContract $pawnContract): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! in_array($pawnContract->store_id, $user->allowedStoreIds(), true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $pawnContract->load(['client', 'item']);

        return response()->json($this->contractPayload($pawnContract, true));
    }

    public function redeem(Request $request, PawnContract $pawnContract): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $contract = $this->redemptionService->redeem($user, $pawnContract);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($this->contractPayload($contract, true));
    }

    public function payInterest(Request $request, PawnContract $pawnContract): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'extend_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        try {
            $contract = $this->redemptionService->payInterest($user, $pawnContract, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($this->contractPayload($contract, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function contractPayload(PawnContract $contract, bool $detailed = false): array
    {
        $contract->loadMissing(['client', 'item']);

        $payload = [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'client_id' => $contract->client_id,
            'item_id' => $contract->item_id,
            'store_id' => $contract->store_id,
            'appraiser_id' => $contract->appraiser_id,
            'loan_amount' => (string) $contract->loan_amount,
            'loan_percent' => (string) $contract->loan_percent,
            'loan_date' => $contract->loan_date?->format('Y-m-d'),
            'expiry_date' => $contract->expiry_date?->format('Y-m-d'),
            'buyback_amount' => $contract->buyback_amount !== null ? (string) $contract->buyback_amount : null,
            'redemption_amount' => (string) $contract->redemption_amount,
            'is_redeemed' => (bool) $contract->is_redeemed,
            'redeemed_at' => $contract->redeemed_at?->toIso8601String(),
            'computed_status' => $contract->computed_status,
        ];

        if ($contract->client) {
            $payload['client'] = [
                'id' => $contract->client->id,
                'full_name' => $contract->client->full_name,
                'last_name' => $contract->client->last_name,
                'first_name' => $contract->client->first_name,
                'patronymic' => $contract->client->patronymic,
                'phone' => $contract->client->phone,
                'email' => $contract->client->email,
            ];
        }

        if ($contract->item) {
            $payload['item'] = $this->itemPayload($contract->item, $detailed);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(Item $item, bool $detailed): array
    {
        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'photos' => $this->photoUrls($item->photos ?? []),
            'store_id' => $item->store_id,
            'status_id' => $item->status_id,
        ];

        if ($detailed) {
            $data += [
                'description' => $item->description,
                'barcode' => $item->barcode,
                'category_id' => $item->category_id,
                'brand_id' => $item->brand_id,
                'storage_location_id' => $item->storage_location_id,
                'initial_price' => $item->initial_price !== null ? (string) $item->initial_price : null,
                'current_price' => $item->current_price !== null ? (string) $item->current_price : null,
            ];
        }

        return $data;
    }

    /**
     * @param  array<int, string>|string|null  $paths
     * @return array<int, array{url: string, path: string}>
     */
    private function photoUrls(array|string|null $paths): array
    {
        $paths = Item::normalizePhotos($paths);

        return collect($paths)
            ->filter()
            ->map(fn (string $path) => [
                'url' => Storage::disk('public')->url($path),
                'path' => $path,
            ])
            ->values()
            ->all();
    }
}
