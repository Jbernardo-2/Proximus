<?php

namespace Tests\Feature\Security;

use App\SecurityEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dynamic_responses_include_security_headers(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(), microphone=()')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeaderMissing('Strict-Transport-Security');

        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("script-src 'self' 'wasm-unsafe-eval'", $policy);
        $this->assertStringContainsString("worker-src 'self' blob:", $policy);
    }

    public function test_https_production_response_enables_hsts_and_strict_csp(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app->detectEnvironment(fn (): string => 'production');
            $response = $this->get('https://localhost/login');

            $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

            $policy = (string) $response->headers->get('Content-Security-Policy');
            $this->assertStringContainsString('upgrade-insecure-requests', $policy);
            $this->assertStringContainsString("style-src 'self'", $policy);
            $this->assertStringNotContainsString("'unsafe-inline'", $policy);
            $this->assertStringNotContainsString('http://localhost:*', $policy);
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_security_configuration_uses_safe_defaults(): void
    {
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame(43200, config('sanctum.expiration'));
        $this->assertSame('proximus_', config('sanctum.token_prefix'));
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        $credentials = [
            'email' => 'rate-limit@example.test',
            'password' => 'credencial-incorrecta',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', $credentials)->assertSessionHasErrors('email');
        }

        $this->post('/login', $credentials)->assertStatus(429);
        $this->assertDatabaseCount('security_audit_logs', 5);
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => SecurityEvent::LoginFailed->value,
        ]);
    }
}
