<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordSecurityEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenController extends Controller
{
    public function store(LoginRequest $request, RecordSecurityEventAction $recordSecurityEvent): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! $user->is_active || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $recordSecurityEvent->handle(
                SecurityEvent::LoginFailed,
                $request,
                subject: $user,
                metadata: [
                    'channel' => 'api',
                    'email' => $request->string('email')->toString(),
                ],
            );

            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        $deviceName = $request->string('device_name', 'aplicación móvil')->toString();
        $abilities = $user->apiAbilities();
        $expirationMinutes = (int) config('sanctum.expiration');
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        $user->forceFill(['last_login_at' => now()])->save();
        $user->tokens()->where('name', $deviceName)->delete();
        $newAccessToken = $user->createToken($deviceName, $abilities, $expiresAt);
        $recordSecurityEvent->handle(
            SecurityEvent::TokenIssued,
            $request,
            actor: $user,
            subject: $user,
            metadata: ['device_name' => $deviceName],
        );

        return response()->json([
            'token' => $newAccessToken->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $newAccessToken->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], 201);
    }

    public function destroy(Request $request, RecordSecurityEventAction $recordSecurityEvent): JsonResponse
    {
        $user = $request->user();
        $currentAccessToken = $user?->currentAccessToken();

        if ($user instanceof User) {
            $metadata = ['channel' => 'api'];

            if ($currentAccessToken instanceof PersonalAccessToken) {
                $metadata['device_name'] = $currentAccessToken->name;
            }

            $recordSecurityEvent->handle(
                SecurityEvent::TokenRevoked,
                $request,
                actor: $user,
                subject: $user,
                metadata: $metadata,
            );
        }

        if ($currentAccessToken instanceof PersonalAccessToken) {
            $currentAccessToken->delete();
        }

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
