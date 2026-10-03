<?php

namespace App\Actions;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordSecurityEventAction
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function handle(
        SecurityEvent $event,
        Request $request,
        ?User $actor = null,
        ?Model $subject = null,
        array $metadata = [],
    ): SecurityAuditLog {
        return SecurityAuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
