<?php

namespace App\Models;

use App\DeliveryRunStatus;
use Database\Factories\DeliveryRunStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_run_id', 'from_status', 'to_status', 'changed_by', 'reason'])]
class DeliveryRunStatusHistory extends Model
{
    /** @use HasFactory<DeliveryRunStatusHistoryFactory> */
    use HasFactory, HasUlids;

    public function deliveryRun(): BelongsTo
    {
        return $this->belongsTo(DeliveryRun::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'from_status' => DeliveryRunStatus::class,
            'to_status' => DeliveryRunStatus::class,
        ];
    }
}
