<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'device_name', 'client_reference', 'type', 'status', 'http_status', 'payload', 'response', 'occurred_at', 'attempts', 'processed_at'])]
class SyncOperation extends Model
{
    use HasUlids;

    public const string ACCEPTED = 'accepted';

    public const string REJECTED = 'rejected';

    public const string FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
