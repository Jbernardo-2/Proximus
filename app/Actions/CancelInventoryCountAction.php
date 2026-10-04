<?php

namespace App\Actions;

use App\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelInventoryCountAction
{
    public function handle(InventoryCount $count, User $actor, string $reason): InventoryCount
    {
        return DB::transaction(function () use ($count, $actor, $reason): InventoryCount {
            $lockedCount = InventoryCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $lockedCount->isDraft()) {
                throw ValidationException::withMessages([
                    'count' => ['Solo se puede cancelar un conteo en proceso.'],
                ]);
            }

            $lockedCount->forceFill([
                'status' => InventoryCountStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            return $lockedCount->refresh();
        });
    }
}
