<?php

namespace App\Models;

use Database\Factories\ProductSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'supplier_id', 'product_presentation_id', 'supplier_sku', 'cost_price', 'is_preferred', 'is_active', 'notes'])]
class ProductSupplier extends Model
{
    /** @use HasFactory<ProductSupplierFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
