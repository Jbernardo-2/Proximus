<?php

namespace App\Models;

use App\OrderPriceSource;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_id', 'product_id', 'product_presentation_id', 'price_tier_id', 'product_sku', 'product_name', 'presentation_name', 'base_unit_symbol', 'conversion_factor', 'quantity', 'base_quantity', 'standard_unit_price', 'unit_price', 'price_source', 'price_overridden_by', 'override_reason', 'line_total', 'notes'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, HasUlids;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id')->withTrashed();
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class)->withTrashed();
    }

    public function priceOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_overridden_by');
    }

    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    public function deliveryRunItems(): HasMany
    {
        return $this->hasMany(DeliveryRunItem::class);
    }

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'standard_unit_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
            'price_source' => OrderPriceSource::class,
        ];
    }
}
