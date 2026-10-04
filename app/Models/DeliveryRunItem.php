<?php

namespace App\Models;

use Database\Factories\DeliveryRunItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_run_order_id', 'order_item_id', 'product_id', 'product_presentation_id', 'product_sku', 'product_name', 'presentation_name', 'base_unit_symbol', 'conversion_factor', 'unit_price', 'requested_quantity', 'requested_base_quantity', 'prepared_quantity', 'prepared_base_quantity', 'loaded_quantity', 'loaded_base_quantity', 'delivered_quantity', 'delivered_base_quantity', 'returned_quantity', 'returned_base_quantity', 'damaged_quantity', 'damaged_base_quantity', 'missing_quantity', 'missing_base_quantity', 'delivered_line_total'])]
class DeliveryRunItem extends Model
{
    /** @use HasFactory<DeliveryRunItemFactory> */
    use HasFactory, HasUlids;

    public function deliveryRunOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'requested_quantity' => 'decimal:6',
            'requested_base_quantity' => 'decimal:6',
            'prepared_quantity' => 'decimal:6',
            'prepared_base_quantity' => 'decimal:6',
            'loaded_quantity' => 'decimal:6',
            'loaded_base_quantity' => 'decimal:6',
            'delivered_quantity' => 'decimal:6',
            'delivered_base_quantity' => 'decimal:6',
            'returned_quantity' => 'decimal:6',
            'returned_base_quantity' => 'decimal:6',
            'damaged_quantity' => 'decimal:6',
            'damaged_base_quantity' => 'decimal:6',
            'missing_quantity' => 'decimal:6',
            'missing_base_quantity' => 'decimal:6',
            'delivered_line_total' => 'decimal:4',
        ];
    }
}
