<?php

namespace App\Models;

use App\InventoryCountStatus;
use Database\Factories\InventoryCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['count_number', 'warehouse_id', 'status', 'counted_on', 'notes', 'created_by', 'posted_at', 'posted_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class InventoryCount extends Model
{
    /** @use HasFactory<InventoryCountFactory> */
    use HasFactory, HasUlids;

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
        return $this->hasMany(InventoryCountItem::class);
    }

    public function inventoryCountItems(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InventoryCountStatus::Draft;
    }

    protected function casts(): array
    {
        return [
            'status' => InventoryCountStatus::class,
            'counted_on' => 'date',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
