<?php

namespace App\Models;

use App\CustomerReceivableSource;
use App\CustomerReceivableStatus;
use Database\Factories\CustomerReceivableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'source', 'order_id', 'delivery_run_order_id', 'document_number', 'original_amount', 'outstanding_amount', 'currency', 'status', 'opened_at', 'issued_at', 'due_date', 'term_days', 'notes', 'paid_at', 'voided_at', 'voided_by', 'void_reason', 'created_by'])]
class CustomerReceivable extends Model
{
    /** @use HasFactory<CustomerReceivableFactory> */
    use HasFactory, HasUlids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryRunOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerReceivableAllocation::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CustomerReceivableStatus::Open->value,
            CustomerReceivableStatus::PartiallyPaid->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOutstanding()
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:4',
            'outstanding_amount' => 'decimal:4',
            'source' => CustomerReceivableSource::class,
            'status' => CustomerReceivableStatus::class,
            'opened_at' => 'datetime',
            'issued_at' => 'date',
            'due_date' => 'date',
            'term_days' => 'integer',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
