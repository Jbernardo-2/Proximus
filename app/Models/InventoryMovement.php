<?php

namespace App\Models;

use App\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'product_presentation_id', 'inventory_document_id', 'inventory_count_id', 'order_id', 'order_item_id', 'delivery_run_id', 'delivery_run_order_id', 'delivery_run_item_id', 'type', 'occurred_at', 'quantity_on_hand_delta', 'quantity_reserved_delta', 'quantity_on_hand_after', 'quantity_reserved_after', 'presentation_quantity', 'conversion_factor', 'product_sku', 'product_name', 'presentation_name', 'base_unit_symbol', 'reference_number', 'lot_number', 'expiration_date', 'reason', 'created_by'])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory, HasUlids;

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id')->withTrashed();
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function deliveryRun(): BelongsTo
    {
        return $this->belongsTo(DeliveryRun::class);
    }

    public function deliveryRunOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunOrder::class);
    }

    public function deliveryRunItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryRunItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'occurred_at' => 'datetime',
            'quantity_on_hand_delta' => 'decimal:6',
            'quantity_reserved_delta' => 'decimal:6',
            'quantity_on_hand_after' => 'decimal:6',
            'quantity_reserved_after' => 'decimal:6',
            'presentation_quantity' => 'decimal:6',
            'conversion_factor' => 'decimal:6',
            'expiration_date' => 'date',
        ];
    }
}
