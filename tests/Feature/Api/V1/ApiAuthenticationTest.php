<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\SecurityEvent;
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
            ->assertJsonPath('abilities.0', 'catalog:manage')
            ->assertJsonPath('abilities.1', 'customers:manage')
            ->assertJsonPath('abilities.2', 'routes:view')
            ->assertJsonPath('abilities.3', 'routes:manage')
            ->assertJsonPath('abilities.4', 'orders:view')
            ->assertJsonPath('abilities.5', 'orders:manage')
            ->assertJsonPath('abilities.6', 'orders:override')
            ->assertJsonPath('abilities.7', 'orders:lifecycle')
            ->assertJsonPath('abilities.8', 'inventory:view')
            ->assertJsonPath('abilities.9', 'inventory:operate')
            ->assertJsonPath('abilities.10', 'inventory:adjust')
            ->assertJsonPath('abilities.11', 'inventory:configure')
            ->assertJsonPath('abilities.12', 'users:manage')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('expires_at', fn (mixed $expiresAt): bool => is_string($expiresAt) && $expiresAt !== '');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'tablet bodega',
        ]);
        $accessToken = $user->tokens()->firstOrFail();
        $this->assertSame(
            ['catalog:manage', 'customers:manage', 'routes:view', 'routes:manage', 'orders:view', 'orders:manage', 'orders:override', 'orders:lifecycle', 'inventory:view', 'inventory:operate', 'inventory:adjust', 'inventory:configure', 'users:manage'],
            $accessToken->abilities,
        );
        $this->assertNotNull($accessToken->expires_at);
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => SecurityEvent::TokenIssued->value,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_invalid_credentials_return_422(): void
    {
        $user = User::factory()->admin()->create(['password' => 'secret-123']);

        $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => null,
            'event' => SecurityEvent::LoginFailed->value,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_request_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_repartidor_token_is_forbidden_from_catalog(): void
    {
        Sanctum::actingAs(User::factory()->repartidor()->create(), ['catalog:manage']);

        $this->getJson('/api/v1/products')->assertForbidden();
    }

    public function test_current_token_can_be_revoked_and_is_audited(): void
    {
        $user = User::factory()->admin()->create();
        $plainTextToken = $user->createToken(
            'tablet ruta',
            ['catalog:manage', 'users:manage'],
            now()->addDay(),
        )->plainTextToken;

        $this->withToken($plainTextToken)
            ->deleteJson('/api/v1/tokens/current')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertCount(0, $user->tokens()->get());
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => SecurityEvent::TokenRevoked->value,
            'subject_id' => (string) $user->id,
        ]);
    }
}
