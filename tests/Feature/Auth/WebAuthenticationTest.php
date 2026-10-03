<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\SecurityEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_screen_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Bienvenido de nuevo')
            ->assertSee('images/proximus-login-hero.webp');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_active_catalog_user_can_sign_in(): void
    {
        $user = User::factory()->admin()->create(['password' => 'secret-123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => SecurityEvent::LoginSucceeded->value,
            'subject_type' => User::class,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->admin()->inactive()->create(['password' => 'secret-123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => null,
            'event' => SecurityEvent::LoginFailed->value,
        ]);
    }

    public function test_repartidor_cannot_enter_catalog_panel(): void
    {
        $user = User::factory()->repartidor()->create(['password' => 'secret-123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => SecurityEvent::LoginDenied->value,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_authenticated_user_can_sign_out(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => SecurityEvent::Logout->value,
            'subject_id' => (string) $user->id,
        ]);
    }
}
