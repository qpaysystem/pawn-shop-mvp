<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API клиентов (маршруты в routes/api.php).
 */
class ClientApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        return response()->json(
            Client::query()->orderByDesc('id')->paginate($perPage)
        );
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($client);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(Client $client): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
