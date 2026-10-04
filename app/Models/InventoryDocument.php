<?php

namespace App\Models;

use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use Database\Factories\InventoryDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['document_number', 'warehouse_id', 'supplier_id', 'type', 'status', 'occurred_on', 'external_reference', 'notes', 'created_by', 'posted_at', 'posted_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class InventoryDocument extends Model
{
    /** @use HasFactory<InventoryDocumentFactory> */
    use HasFactory, HasUlids;

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function inventoryDocumentItems(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InventoryDocumentStatus::Draft;
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryDocumentType::class,
            'status' => InventoryDocumentStatus::class,
            'occurred_on' => 'date',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
