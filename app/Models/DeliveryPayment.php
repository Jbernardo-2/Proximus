<?php

namespace App\Models;

use App\DeliveryPaymentStatus;
use App\PaymentMethod;
use Database\Factories\DeliveryPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['receipt_number', 'client_reference', 'delivery_run_order_id', 'status', 'method', 'amount', 'reference', 'notes', 'received_at', 'received_by', 'voided_at', 'voided_by', 'void_reason'])]
class DeliveryPayment extends Model
{
    /** @use HasFactory<DeliveryPaymentFactory> */
    use HasFactory, HasUlids;

    public function deliveryRunOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryPaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:4',
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
