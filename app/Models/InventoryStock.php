<?php

namespace App\Models;

use Database\Factories\InventoryStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'quantity_on_hand', 'quantity_reserved', 'reorder_point'])]
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory, HasUlids;

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function availableQuantity(): string
    {
        return bcsub((string) $this->quantity_on_hand, (string) $this->quantity_reserved, 6);
    }

    public function shortageQuantity(): string
    {
        $available = $this->availableQuantity();

        return bccomp($available, '0', 6) < 0 ? bcmul($available, '-1', 6) : '0.000000';
    }

    public function isLowStock(): bool
    {
        return bccomp($this->availableQuantity(), (string) $this->reorder_point, 6) <= 0;
    }

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:6',
            'quantity_reserved' => 'decimal:6',
            'reorder_point' => 'decimal:6',
        ];
    }
}
