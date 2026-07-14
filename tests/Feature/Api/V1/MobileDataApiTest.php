<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Tests\TestCase;

class MobileDataApiTest extends TestCase
{
    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_catalogs_require_auth(): void
    {
        $this->getJson('/api/v1/stores')->assertUnauthorized();
    }

    public function test_pawn_contracts_require_auth(): void
    {
        $this->getJson('/api/v1/pawn-contracts')->assertUnauthorized();
    }

    public function test_stores_list_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->getJson('/api/v1/stores', [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ]);

        $response->assertOk()->assertJsonIsArray();
    }

    public function test_clients_list_shape(): void
    {
        $user = User::factory()->create();
        $response = $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_clients_index_requires_auth(): void
    {
        $this->getJson('/api/v1/clients')->assertUnauthorized();
    }

    public function test_pawn_contracts_list_shape(): void
    {
        $user = User::factory()->create();
        $response = $this->getJson('/api/v1/pawn-contracts?status=active', [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_parse_passport_requires_auth(): void
    {
        $this->postJson('/api/v1/tools/parse-passport')->assertUnauthorized();
    }

    public function test_parse_passport_requires_photo(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/tools/parse-passport', [], [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }
}
