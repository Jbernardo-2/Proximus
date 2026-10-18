<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\SecurityEvent;
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
            'password' => 'BodegaSegura2026',
            'password_confirmation' => 'BodegaSegura2026',
        ]);

        $response->assertRedirect(route('users.index'));
        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('María López', $user->name);
        $this->assertSame(UserRole::Bodeguero, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('BodegaSegura2026', $user->password));
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $administrator->id,
            'event' => SecurityEvent::UserCreated->value,
            'subject_id' => (string) $user->id,
        ]);
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
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ]);

        $response->assertRedirect(route('users.index'));
        $user->refresh();
        $this->assertSame(UserRole::Supervisor, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        $this->assertCount(0, $user->tokens);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $administrator->id,
            'event' => SecurityEvent::UserUpdated->value,
            'subject_id' => (string) $user->id,
        ]);
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

    public function test_a_password_needs_eight_characters_with_a_letter_and_a_number_and_is_typed_once(): void
    {
        $administrator = User::factory()->admin()->create();
        $user = fn (string $password): array => [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.com',
            'role' => UserRole::Bodeguero->value,
            'is_active' => '1',
            'password' => $password,
        ];

        foreach (['corta12', 'solotexto', '12345678'] as $weak) {
            $this->actingAs($administrator)->post(route('users.store'), $user($weak))->assertSessionHasErrors('password');
        }
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);

        $this->actingAs($administrator)->post(route('users.store'), $user('Mango-4827'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
    }

    public function test_the_user_form_explains_each_role_and_keeps_the_password_closed_when_editing(): void
    {
        $administrator = User::factory()->admin()->create();
        $seller = User::factory()->preventista()->create();

        $this->actingAs($administrator)->get(route('users.create'))
            ->assertOk()
            ->assertSee('data-password-generate="password"', false)
            ->assertSee(UserRole::Repartidor->description())
            ->assertDontSee('password_confirmation');
        $this->actingAs($administrator)->get(route('users.edit', $seller))
            ->assertOk()
            ->assertSee('Cambiar contraseña')
            ->assertSee('id="password-fields" class="hidden', false);
    }

    public function test_a_taken_email_says_whose_it_is_and_suggests_reactivating_an_inactive_user(): void
    {
        $administrator = User::factory()->admin()->create();
        User::factory()->preventista()->create(['name' => 'Ana López', 'email' => 'ana@example.com']);
        User::factory()->repartidor()->create(['name' => 'Luis Mejía', 'email' => 'luis@example.com', 'is_active' => false]);
        $user = fn (string $email): array => ['name' => 'Otro', 'email' => $email, 'role' => UserRole::Bodeguero->value, 'is_active' => '1', 'password' => 'Mango-4827'];

        $this->actingAs($administrator)->post(route('users.store'), $user(' ANA@example.com '))
            ->assertSessionHasErrors(['email' => 'Ya existe un usuario con este correo: Ana López (Preventista).']);
        $this->actingAs($administrator)->post(route('users.store'), $user('luis@example.com'))
            ->assertSessionHasErrors(['email' => 'Este correo es de Luis Mejía (Repartidor), que está desactivado. Reactívalo en lugar de crear otro usuario.']);

        $this->actingAs($administrator)->getJson(route('users.email-check', ['email' => 'Ana@Example.com']))
            ->assertOk()
            ->assertJsonPath('taken', true)
            ->assertJsonPath('message', 'Ya existe un usuario con este correo: Ana López (Preventista).');
        $this->actingAs($administrator)->getJson(route('users.email-check', ['email' => 'nadie@example.com']))->assertJsonPath('taken', false);
        $ana = User::query()->where('email', 'ana@example.com')->sole();
        $this->actingAs($administrator)->getJson(route('users.email-check', ['email' => 'ana@example.com', 'ignore' => $ana->id]))
            ->assertJsonPath('taken', false, 'editing Ana, her own address is not taken');

        $seller = User::factory()->preventista()->create();
        $this->actingAs($seller)->getJson(route('users.email-check', ['email' => 'ana@example.com']))->assertForbidden();
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
