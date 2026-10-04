<?php

namespace App\Models;

use App\DeliveryRunStatus;
use App\UserRole;
use Database\Factories\DeliveryRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['run_number', 'client_reference', 'warehouse_id', 'driver_id', 'vehicle_id', 'scheduled_date', 'status', 'warehouse_code', 'warehouse_name', 'driver_name', 'vehicle_code', 'vehicle_license_plate', 'notes', 'created_by', 'preparation_started_at', 'preparation_started_by', 'loaded_at', 'loaded_by', 'departed_at', 'departed_by', 'settled_at', 'settled_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'loaded_total', 'delivered_total', 'collected_total', 'cash_expected', 'cash_declared', 'cash_difference', 'transfer_total', 'card_total', 'check_total', 'other_payment_total', 'credit_total', 'settlement_notes'])]
class DeliveryRun extends Model
{
    /** @use HasFactory<DeliveryRunFactory> */
    use HasFactory, HasUlids;

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function preparationStartedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparation_started_by');
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function departedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'departed_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function runOrders(): HasMany
    {
        return $this->hasMany(DeliveryRunOrder::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryRunStatusHistory::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            DeliveryPayment::class,
            DeliveryRunOrder::class,
            'delivery_run_id',
            'delivery_run_order_id',
        );
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Supervisor, UserRole::Bodeguero => $query,
            UserRole::Repartidor => $query->where('driver_id', $user->id),
            UserRole::Preventista => $query->whereRaw('1 = 0'),
        };
    }

    public function isDraft(): bool
    {
        return $this->status === DeliveryRunStatus::Draft;
    }

    public function isPreparing(): bool
    {
        return $this->status === DeliveryRunStatus::Preparing;
    }

    public function canRecordRouteActivity(): bool
    {
        return in_array($this->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status' => DeliveryRunStatus::class,
            'preparation_started_at' => 'datetime',
            'loaded_at' => 'datetime',
            'departed_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'loaded_total' => 'decimal:4',
            'delivered_total' => 'decimal:4',
            'collected_total' => 'decimal:4',
            'cash_expected' => 'decimal:4',
            'cash_declared' => 'decimal:4',
            'cash_difference' => 'decimal:4',
            'transfer_total' => 'decimal:4',
            'card_total' => 'decimal:4',
            'check_total' => 'decimal:4',
            'other_payment_total' => 'decimal:4',
            'credit_total' => 'decimal:4',
        ];
    }
}
