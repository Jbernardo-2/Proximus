<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordSecurityEventAction;
use App\Actions\RegisterDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Device;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenController extends Controller
{
    public function store(LoginRequest $request, RecordSecurityEventAction $recordSecurityEvent, RegisterDeviceAction $registerDevice): JsonResponse
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
        $installationId = $request->validated('installation_id');
        $knownDevice = $installationId === null ? null : Device::query()->where('installation_id', $installationId)->first();

        if ($knownDevice?->isRevoked()) {
            $recordSecurityEvent->handle(
                SecurityEvent::LoginDenied,
                $request,
                subject: $user,
                metadata: ['channel' => 'api', 'device_code' => $knownDevice->code, 'reason' => 'device_revoked'],
            );

            return response()->json([
                'message' => 'Este teléfono fue bloqueado por el administrador. Solicita que lo reactive.',
                'code' => 'device_revoked',
            ], 423);
        }

        $abilities = $user->apiAbilities();
        $expirationMinutes = (int) config('sanctum.expiration');
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        $user->forceFill(['last_login_at' => now()])->save();
        $device = null;

        if ($installationId !== null) {
            [$device, $newAccessToken] = $registerDevice->handle($user, [
                'installation_id' => $installationId,
                'name' => $deviceName,
                'platform' => $request->validated('platform'),
                'app_version' => $request->validated('app_version'),
            ], $abilities, $expiresAt);
        } else {
            $user->tokens()->where('name', $deviceName)->delete();
            $newAccessToken = $user->createToken($deviceName, $abilities, $expiresAt);
        }

        $recordSecurityEvent->handle(
            SecurityEvent::TokenIssued,
            $request,
            actor: $user,
            subject: $user,
            metadata: array_filter(['device_name' => $deviceName, 'device_code' => $device?->code]),
        );

        return response()->json([
            'token' => $newAccessToken->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $newAccessToken->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
            'device' => $device === null ? null : ['id' => $device->id, 'code' => $device->code, 'series' => $device->series()],
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
