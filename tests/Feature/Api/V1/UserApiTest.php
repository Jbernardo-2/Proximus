<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\SecurityEvent;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_list_and_filter_users(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['users:manage']);
        User::factory()->create(['name' => 'Bodega Central']);
        User::factory()->create([
            'name' => 'Repartidor Norte',
            'role' => UserRole::Repartidor,
        ]);

        $this->getJson('/api/v1/users?search=Repartidor&role=repartidor')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Repartidor Norte')
            ->assertJsonPath('data.0.role', 'repartidor');
    }

    public function test_administrator_can_create_and_update_user(): void
    {
        $administrator = User::factory()->admin()->create();
        Sanctum::actingAs($administrator, ['users:manage']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Preventista Ruta Uno',
            'email' => 'PREVENTA@EXAMPLE.COM',
            'role' => UserRole::Preventista->value,
            'is_active' => true,
            'password' => 'PreventaSegura2026',
            'password_confirmation' => 'PreventaSegura2026',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'preventa@example.com')
            ->assertJsonPath('data.role', 'preventista')
            ->assertJsonPath('data.is_active', true);

        $user = User::query()->where('email', 'preventa@example.com')->firstOrFail();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $administrator->id,
            'event' => SecurityEvent::UserCreated->value,
            'subject_id' => (string) $user->id,
        ]);

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Preventista Ruta Norte',
            'email' => $user->email,
            'role' => UserRole::Preventista->value,
            'is_active' => false,
            'password' => 'Actualizada2026',
            'password_confirmation' => 'Actualizada2026',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Preventista Ruta Norte')
            ->assertJsonPath('data.is_active', false);

        $this->assertTrue(Hash::check('Actualizada2026', $user->refresh()->password));
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $administrator->id,
            'event' => SecurityEvent::UserUpdated->value,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_non_administrator_is_forbidden_from_user_api(): void
    {
        Sanctum::actingAs(User::factory()->supervisor()->create(), ['users:manage']);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_administrator_cannot_deactivate_own_account_through_api(): void
    {
        $administrator = User::factory()->admin()->create();
        Sanctum::actingAs($administrator, ['users:manage']);

        $this->putJson("/api/v1/users/{$administrator->id}", [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->assertTrue($administrator->refresh()->is_active);
    }

    public function test_administrator_token_without_user_ability_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);

        $this->getJson('/api/v1/users')->assertForbidden();
    }
}
