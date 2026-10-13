<?php

namespace App\Models;

use Database\Factories\DeliverySettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_number', 'delivery_run_order_id', 'total_amount', 'currency', 'settled_at', 'settled_by'])]
class DeliverySettlement extends Model
{
    /** @use HasFactory<DeliverySettlementFactory> */
    use HasFactory, HasUlids;

    public function deliveryRunOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunOrder::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliverySettlementLine::class);
    }

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'settled_at' => 'datetime',
        ];
    }
}
