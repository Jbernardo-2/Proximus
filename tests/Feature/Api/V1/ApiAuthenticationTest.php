<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_create_token_and_return_201(): void
    {
        $user = User::factory()->admin()->create(['password' => 'secret-123']);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'secret-123',
            'device_name' => 'tablet bodega',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'tablet bodega',
        ]);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_invalid_credentials_return_422(): void
    {
        $user = User::factory()->admin()->create(['password' => 'secret-123']);

        $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_request_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_repartidor_token_is_forbidden_from_catalog(): void
    {
        Sanctum::actingAs(User::factory()->repartidor()->create());

        $this->getJson('/api/v1/products')->assertForbidden();
    }
}
