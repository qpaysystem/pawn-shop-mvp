<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Mobile v1: список, поиск и создание клиентов. */
class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        $q = (string) $request->get('q', $request->get('search', ''));
        if ($q !== '') {
            $query->matchingSearch($q);
        }

        if ($request->boolean('blacklist')) {
            $query->where('blacklist_flag', true);
        }

        $paginator = $query
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Client $c) => $this->clientPayload($c))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Client $client): JsonResponse
    {
        $client->loadCount([
            'pawnContracts as active_pawn_contracts_count' => fn ($q) => $q->where('is_redeemed', false),
        ]);

        return response()->json($this->clientPayload($client, true));
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < Client::searchQueryMinLength($q)) {
            return response()->json(['data' => []]);
        }

        $clients = Client::query()
            ->matchingSearch($q)
            ->limit(20)
            ->get(['id', 'full_name', 'last_name', 'first_name', 'patronymic', 'phone', 'email']);

        return response()->json(['data' => $clients]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'patronymic' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50', 'unique:clients,phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'passport_data' => ['nullable', 'string', 'max:500'],
        ]);

        $client = Client::create([
            'client_type' => Client::TYPE_INDIVIDUAL,
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'patronymic' => $data['patronymic'] ?? null,
            'full_name' => trim(implode(' ', array_filter([
                $data['last_name'],
                $data['first_name'],
                $data['patronymic'] ?? null,
            ]))),
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'passport_data' => $data['passport_data'] ?? null,
            'blacklist_flag' => false,
        ]);

        return response()->json($this->clientPayload($client), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client, bool $detailed = false): array
    {
        $payload = [
            'id' => $client->id,
            'client_type' => $client->client_type,
            'full_name' => $client->full_name,
            'last_name' => $client->last_name,
            'first_name' => $client->first_name,
            'patronymic' => $client->patronymic,
            'phone' => $client->phone,
            'email' => $client->email,
            'passport_data' => $client->passport_data,
            'blacklist_flag' => (bool) $client->blacklist_flag,
        ];

        if ($detailed) {
            $payload += [
                'legal_name' => $client->legal_name,
                'inn' => $client->inn,
                'notes' => $client->notes,
                'active_pawn_contracts_count' => (int) ($client->active_pawn_contracts_count ?? 0),
            ];
        }

        return $payload;
    }
}
