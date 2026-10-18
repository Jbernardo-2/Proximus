<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExpiredFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips the token check in tests; these are about what happens when it fails.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_signing_in_from_an_expired_login_page_goes_back_to_it_with_the_email_and_a_clear_message(): void
    {
        $user = User::factory()->admin()->create();

        $this->from('/login')
            ->post('/login', ['_token' => 'vencido', 'email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['session' => 'La página de inicio se venció por seguridad. Escribe tu contraseña otra vez.'])
            ->assertSessionHasInput('email', $user->email)
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();

        $this->from('/login')->followingRedirects()
            ->post('/login', ['_token' => 'vencido', 'email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertSee('se venció por seguridad')
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_after_that_the_fresh_login_page_signs_in(): void
    {
        $user = User::factory()->admin()->create();
        $this->from('/login')->post('/login', ['_token' => 'vencido', 'email' => $user->email, 'password' => 'password']);

        $this->post('/login', ['_token' => session()->token(), 'email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_phone_api_still_gets_a_plain_error(): void
    {
        $this->postJson('/login', ['_token' => 'vencido'])->assertStatus(419);
    }
}
