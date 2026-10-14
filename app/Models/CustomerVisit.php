<?php

namespace App\Models;

use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\VisitLocationStatus;
use Database\Factories\CustomerVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['client_reference', 'customer_id', 'user_id', 'type', 'subject_type', 'subject_id', 'reason', 'notes', 'location_status', 'latitude', 'longitude', 'accuracy_meters', 'distance_meters', 'occurred_at'])]
class CustomerVisit extends Model
{
    /** @use HasFactory<CustomerVisitFactory> */
    use HasFactory, HasUlids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'type' => CustomerVisitType::class,
            'reason' => CustomerVisitReason::class,
            'location_status' => VisitLocationStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:1',
            'occurred_at' => 'datetime',
        ];
    }
}
