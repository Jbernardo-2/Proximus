<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
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
        Sanctum::actingAs(User::factory()->admin()->create());
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
        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Preventista Ruta Uno',
            'email' => 'PREVENTA@EXAMPLE.COM',
            'role' => UserRole::Preventista->value,
            'is_active' => true,
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'preventa@example.com')
            ->assertJsonPath('data.role', 'preventista')
            ->assertJsonPath('data.is_active', true);

        $user = User::query()->where('email', 'preventa@example.com')->firstOrFail();

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Preventista Ruta Norte',
            'email' => $user->email,
            'role' => UserRole::Preventista->value,
            'is_active' => false,
            'password' => 'updated-123',
            'password_confirmation' => 'updated-123',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Preventista Ruta Norte')
            ->assertJsonPath('data.is_active', false);

        $this->assertTrue(Hash::check('updated-123', $user->refresh()->password));
    }

    public function test_non_administrator_is_forbidden_from_user_api(): void
    {
        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_administrator_cannot_deactivate_own_account_through_api(): void
    {
        $administrator = User::factory()->admin()->create();
        Sanctum::actingAs($administrator);

        $this->putJson("/api/v1/users/{$administrator->id}", [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->assertTrue($administrator->refresh()->is_active);
    }
}
