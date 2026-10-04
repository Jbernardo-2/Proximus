<?php

namespace App\Models;

use Database\Factories\InventoryDocumentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_document_id', 'product_id', 'product_presentation_id', 'product_sku', 'product_name', 'presentation_name', 'base_unit_symbol', 'conversion_factor', 'quantity', 'base_quantity', 'unit_cost', 'lot_number', 'expiration_date', 'notes'])]
class InventoryDocumentItem extends Model
{
    /** @use HasFactory<InventoryDocumentItemFactory> */
    use HasFactory, HasUlids;

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
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
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'expiration_date' => 'date',
        ];
    }
}
