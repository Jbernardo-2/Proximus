<?php

namespace App\Models;

use App\SettlementMethod;
use Database\Factories\DeliverySettlementLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_settlement_id', 'method', 'amount', 'reference', 'delivery_payment_id', 'customer_receivable_id'])]
class DeliverySettlementLine extends Model
{
    /** @use HasFactory<DeliverySettlementLineFactory> */
    use HasFactory, HasUlids;

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DeliverySettlement::class, 'delivery_settlement_id');
    }

    public function deliveryPayment(): BelongsTo
    {
        return $this->belongsTo(DeliveryPayment::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(CustomerReceivable::class, 'customer_receivable_id');
    }

    protected function casts(): array
    {
        return [
            'method' => SettlementMethod::class,
            'amount' => 'decimal:4',
        ];
    }
}
