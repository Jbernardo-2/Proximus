<?php

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Sanctum's own text reaches the phone as the reason an operation was rejected.
        $exceptions->render(fn (MissingAbilityException $exception, Request $request) => $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => 'Tu sesión no tiene permiso para esta operación. Sincroniza con señal o vuelve a iniciar sesión.'], 403)
            : null);
        // A form left open past the session (or restored by the browser) goes back with what was typed instead of "Page expired".
        $exceptions->render(fn (HttpException $exception, Request $request) => $exception->getStatusCode() === 419 && ! $request->expectsJson() && ! $request->is('api/*')
            ? back()
                ->withInput(Arr::except($request->input(), ['_token', 'password', 'password_confirmation', 'current_password']))
                ->withErrors(['session' => $request->routeIs('login') || $request->is('login')
                    ? 'La página de inicio se venció por seguridad. Escribe tu contraseña otra vez.'
                    : 'La página estuvo abierta mucho tiempo y se venció por seguridad. Revisa los datos y vuelve a enviarlos.'])
            : null);
    })->create();
