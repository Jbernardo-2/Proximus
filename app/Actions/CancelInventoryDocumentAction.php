<?php

namespace App\Actions;

use App\InventoryDocumentStatus;
use App\Models\InventoryDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelInventoryDocumentAction
{
    public function handle(InventoryDocument $document, User $actor, string $reason): InventoryDocument
    {
        return DB::transaction(function () use ($document, $actor, $reason): InventoryDocument {
            $lockedDocument = InventoryDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (! $lockedDocument->isDraft()) {
                throw ValidationException::withMessages([
                    'document' => ['Solo se puede cancelar un movimiento en borrador. Los aplicados deben corregirse con otro movimiento.'],
                ]);
            }

            $lockedDocument->forceFill([
                'status' => InventoryDocumentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            return $lockedDocument->refresh();
        });
    }
}
