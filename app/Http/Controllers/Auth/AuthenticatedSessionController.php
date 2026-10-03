<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordSecurityEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, RecordSecurityEventAction $recordSecurityEvent): RedirectResponse
    {
        $authenticated = Auth::attempt([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $authenticated) {
            $recordSecurityEvent->handle(
                SecurityEvent::LoginFailed,
                $request,
                metadata: [
                    'channel' => 'web',
                    'email' => $request->string('email')->toString(),
                ],
            );

            return back()
                ->withErrors(['email' => 'Las credenciales proporcionadas no son válidas o el usuario está inactivo.'])
                ->onlyInput('email');
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessPanel()) {
            $recordSecurityEvent->handle(
                SecurityEvent::LoginDenied,
                $request,
                actor: $user,
                subject: $user,
                metadata: ['channel' => 'web', 'role' => $user->role->value],
            );
            Auth::logout();

            return back()
                ->withErrors(['email' => 'Este usuario todavía no tiene acceso al panel de Proximus.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $recordSecurityEvent->handle(
            SecurityEvent::LoginSucceeded,
            $request,
            actor: $user,
            subject: $user,
            metadata: ['channel' => 'web'],
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, RecordSecurityEventAction $recordSecurityEvent): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $recordSecurityEvent->handle(
                SecurityEvent::Logout,
                $request,
                actor: $user,
                subject: $user,
                metadata: ['channel' => 'web'],
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
