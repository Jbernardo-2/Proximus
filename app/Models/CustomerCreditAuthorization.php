<?php

namespace App\Models;

use App\CustomerCreditAuthorizationType;
use Database\Factories\CustomerCreditAuthorizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'type', 'order_id', 'delivery_run_order_id', 'requested_amount', 'exposure_before', 'credit_limit', 'exceeded_by', 'reason', 'authorized_by', 'authorized_at'])]
class CustomerCreditAuthorization extends Model
{
    /** @use HasFactory<CustomerCreditAuthorizationFactory> */
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

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    protected function casts(): array
    {
        return [
            'type' => CustomerCreditAuthorizationType::class,
            'requested_amount' => 'decimal:4',
            'exposure_before' => 'decimal:4',
            'credit_limit' => 'decimal:4',
            'exceeded_by' => 'decimal:4',
            'authorized_at' => 'datetime',
        ];
    }
}
