<?php

namespace App\Models;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\PaymentMethod;
use Database\Factories\CustomerAccountEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['customer_id', 'type', 'status', 'receipt_number', 'order_id', 'delivery_run_order_id', 'delivery_payment_id', 'method', 'debit_amount', 'credit_amount', 'currency', 'reference', 'notes', 'receipt_snapshot', 'occurred_at', 'created_by', 'reversal_of_id', 'reversed_at', 'reversed_by'])]
class CustomerAccountEntry extends Model
{
    /** @use HasFactory<CustomerAccountEntryFactory> */
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

    public function deliveryPayment(): BelongsTo
    {
        return $this->belongsTo(DeliveryPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerReceivableAllocation::class);
    }

    public function paymentLines(): HasMany
    {
        return $this->hasMany(CustomerPaymentLine::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CustomerAccountEntryType::class,
            'status' => CustomerAccountEntryStatus::class,
            'method' => PaymentMethod::class,
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'receipt_snapshot' => 'array',
            'occurred_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
