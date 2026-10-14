<?php

namespace App\Actions;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues the token of a phone installation, replacing the one it had before.
 * The phone keeps its code (and therefore its document series) across logins.
 */
class RegisterDeviceAction
{
    /**
     * @param  array{installation_id: string, name: string, platform?: ?string, app_version?: ?string}  $data
     * @param  list<string>  $abilities
     * @return array{0: Device, 1: NewAccessToken}
     */
    public function handle(User $user, array $data, array $abilities, mixed $expiresAt): array
    {
        return DB::transaction(function () use ($user, $data, $abilities, $expiresAt): array {
            $device = Device::query()->where('installation_id', $data['installation_id'])->lockForUpdate()->first();

            if ($device?->personal_access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($device->personal_access_token_id)->delete();
            }

            $token = $user->createToken($data['name'], $abilities, $expiresAt);
            $attributes = [
                'name' => $data['name'],
                'platform' => $data['platform'] ?? $device?->platform,
                'app_version' => $data['app_version'] ?? $device?->app_version,
                'user_id' => $user->id,
                'personal_access_token_id' => $token->accessToken->getKey(),
                'last_seen_at' => now(),
            ];

            if ($device !== null) {
                $device->forceFill($attributes)->save();

                return [$device, $token];
            }

            for ($attempt = 1; ; $attempt++) {
                try {
                    $device = DB::transaction(fn (): Device => Device::query()->create([
                        ...$attributes,
                        'installation_id' => $data['installation_id'],
                        'code' => Device::nextCode(),
                    ]));

                    return [$device, $token];
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt >= 3) {
                        throw $exception;
                    }
                }
            }
        });
    }
}
