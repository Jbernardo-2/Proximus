<?php

namespace App\Models;

use Database\Factories\InventoryCountItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_count_id', 'product_id', 'product_sku', 'product_name', 'base_unit_symbol', 'expected_quantity', 'counted_quantity', 'difference', 'notes'])]
class InventoryCountItem extends Model
{
    /** @use HasFactory<InventoryCountItemFactory> */
    use HasFactory, HasUlids;

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:6',
            'counted_quantity' => 'decimal:6',
            'difference' => 'decimal:6',
        ];
    }
}
