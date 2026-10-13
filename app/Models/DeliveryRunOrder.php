<?php

namespace App\Models;

use App\DeliveryOrderStatus;
use App\DeliveryOutcomeReason;
use Database\Factories\DeliveryRunOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['delivery_run_id', 'order_id', 'visit_order', 'status', 'outcome_status', 'prepared_at', 'prepared_by', 'requested_total', 'delivered_total', 'collected_total', 'balance_due', 'outcome_reason', 'outcome_notes', 'outcome_recorded_at', 'outcome_recorded_by', 'credit_reason', 'receiver_name', 'latitude', 'longitude', 'completed_at', 'completed_by'])]
class DeliveryRunOrder extends Model
{
    /** @use HasFactory<DeliveryRunOrderFactory> */
    use HasFactory, HasUlids;

    public function deliveryRun(): BelongsTo
    {
        return $this->belongsTo(DeliveryRun::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryRunItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DeliveryPayment::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(DeliverySettlement::class);
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(CustomerReceivable::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function outcomeRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_recorded_by');
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryOrderStatus::class,
            'outcome_status' => DeliveryOrderStatus::class,
            'outcome_reason' => DeliveryOutcomeReason::class,
            'requested_total' => 'decimal:4',
            'delivered_total' => 'decimal:4',
            'collected_total' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'prepared_at' => 'datetime',
            'outcome_recorded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
