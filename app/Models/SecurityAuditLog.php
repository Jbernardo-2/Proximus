<?php

namespace App\Models;

use App\SecurityEvent;
use Database\Factories\SecurityAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'event', 'subject_type', 'subject_id', 'ip_address', 'user_agent', 'metadata'])]
class SecurityAuditLog extends Model
{
    /** @use HasFactory<SecurityAuditLogFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SecurityEvent::class,
            'metadata' => 'array',
        ];
    }
}
