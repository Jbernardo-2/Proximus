<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_view_user_management_and_navigation(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertSee(route('users.create'), false)
            ->assertSee($administrator->email);
    }

    public function test_non_administrator_cannot_manage_users(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_user_with_normalized_email(): void
    {
        $administrator = User::factory()->admin()->create();

        $response = $this->actingAs($administrator)->post(route('users.store'), [
            'name' => '  María López  ',
            'email' => '  MARIA@EXAMPLE.COM  ',
            'role' => UserRole::Bodeguero->value,
            'is_active' => '1',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ]);

        $response->assertRedirect(route('users.index'));
        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('María López', $user->name);
        $this->assertSame(UserRole::Bodeguero, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-123', $user->password));
    }

    public function test_administrator_can_change_access_and_reset_another_users_password(): void
    {
        $administrator = User::factory()->admin()->create();
        $user = User::factory()->create(['password' => 'old-password']);
        $user->createToken('tablet bodega');

        $response = $this->actingAs($administrator)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Supervisor->value,
            'is_active' => '0',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('users.index'));
        $user->refresh();
        $this->assertSame(UserRole::Supervisor, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertCount(0, $user->tokens);
    }

    public function test_empty_password_keeps_the_current_password_when_updating(): void
    {
        $administrator = User::factory()->admin()->create();
        $user = User::factory()->create(['password' => 'current-password']);

        $this->actingAs($administrator)->put(route('users.update', $user), [
            'name' => 'Nombre actualizado',
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => '1',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('current-password', $user->refresh()->password));
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::Admin->value,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($administrator->refresh()->is_active);
    }

    public function test_administrator_cannot_remove_own_administrator_role(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => UserRole::Supervisor->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $administrator->refresh()->role);
    }
}
