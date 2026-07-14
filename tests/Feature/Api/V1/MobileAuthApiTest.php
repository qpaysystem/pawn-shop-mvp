<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_mobile_login(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('secret-pass'),
            'role' => User::ROLE_APPRAISER,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile@example.com',
            'password' => 'secret-pass',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'mobile@example.com')
            ->assertJsonPath('user.role', User::ROLE_APPRAISER);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => 'secret-pass',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_auth_me_returns_current_user_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'role' => User::ROLE_MANAGER,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'me@example.com',
            'password' => 'password',
        ]);

        $token = $login->json('token');

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'me@example.com')
            ->assertJsonPath('role', User::ROLE_MANAGER);
    }

    public function test_auth_me_without_token_is_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'password',
        ]);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertNull(PersonalAccessToken::findToken($token));

        // New HTTP request: guard must not reuse user from the logout request.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
