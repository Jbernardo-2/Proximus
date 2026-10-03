<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(), microphone=()');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $developmentHttpSources = app()->isProduction()
            ? ''
            : ' http://localhost:* http://127.0.0.1:*';
        $developmentConnectSources = app()->isProduction()
            ? ''
            : $developmentHttpSources.' ws://localhost:* ws://127.0.0.1:*';
        $styleSources = app()->isProduction()
            ? "'self'"
            : "'self' 'unsafe-inline'{$developmentHttpSources}";

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self'{$developmentConnectSources}",
            "font-src 'self' data:{$developmentHttpSources}",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: blob:{$developmentHttpSources}",
            "media-src 'self' blob:",
            "object-src 'none'",
            "script-src 'self' 'wasm-unsafe-eval'{$developmentHttpSources}",
            "style-src {$styleSources}",
            "worker-src 'self' blob:",
        ];

        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
