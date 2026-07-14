<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Mobile API v1: Sanctum bearer auth (не затрагивает web session login). */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->getAuthPassword())) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $deviceName = $request->input('device_name', 'mobile');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->userPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, store_id: int|null, store_name: string|null}
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('store');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'store_id' => $user->store_id,
            'store_name' => $user->store?->name,
            'permissions' => [
                'can_create_contracts' => $user->canCreateContracts(),
                'can_process_sales' => $user->canProcessSales(),
                'can_manage_storage' => $user->canManageStorage(),
            ],
        ];
    }
}
