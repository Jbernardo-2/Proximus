<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A phone running the mobile app. Its code identifies the document series it may
 * number by itself while offline (e.g. FAC-M001-000045).
 */
#[Fillable(['installation_id', 'code', 'name', 'platform', 'app_version', 'user_id', 'personal_access_token_id', 'last_seen_at', 'revoked_at', 'revoked_by'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasUlids;

    /** Document scope => [prefix, table, column]. */
    public const array SERIES = [
        'invoice' => ['FAC', 'delivery_settlements', 'invoice_number'],
        'receipt' => ['REC', 'delivery_payments', 'receipt_number'],
        'customer_payment' => ['ABO', 'customer_account_entries', 'receipt_number'],
    ];

    /** The registered phone behind the token the user is authenticated with. */
    public static function current(User $user): ?self
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        $device = static::query()->where('personal_access_token_id', $token->getKey())->first();

        if ($device !== null && ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute()))) {
            $device->forceFill(['last_seen_at' => now(), 'user_id' => $user->id])->saveQuietly();
        }

        return $device;
    }

    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'M%')->orderByRaw('length(code) desc')->orderByDesc('code')->value('code');

        return sprintf('M%03d', ((int) substr((string) $last, 1)) + 1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function syncOperations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function seriesPrefix(string $scope): string
    {
        return self::SERIES[$scope][0].'-'.$this->code.'-';
    }

    /** Whether the number belongs to this device's series for the scope. */
    public function ownsNumber(string $scope, string $number): bool
    {
        return isset(self::SERIES[$scope])
            && preg_match('/^'.preg_quote($this->seriesPrefix($scope), '/').'\d{6}$/', $number) === 1;
    }

    /**
     * Last number the server holds for each series, so a reinstalled or restored app
     * can continue without repeating a printed number.
     *
     * @return array<string, array{prefix: string, last_number: int}>
     */
    public function series(): array
    {
        return collect(self::SERIES)->mapWithKeys(function (array $definition, string $scope): array {
            [, $table, $column] = $definition;
            $prefix = $this->seriesPrefix($scope);
            $last = DB::table($table)->where($column, 'like', $prefix.'%')->max($column);

            return [$scope => [
                'prefix' => $prefix,
                'last_number' => $last === null ? 0 : (int) substr((string) $last, strlen($prefix)),
            ]];
        })->all();
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
