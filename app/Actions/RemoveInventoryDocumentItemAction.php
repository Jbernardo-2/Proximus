<?php

namespace App\Actions;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveInventoryDocumentItemAction
{
    public function handle(InventoryDocument $document, InventoryDocumentItem $item): void
    {
        DB::transaction(function () use ($document, $item): void {
            $lockedDocument = InventoryDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (! $lockedDocument->isDraft()) {
                throw ValidationException::withMessages([
                    'document' => ['Solo se pueden eliminar líneas de un movimiento en borrador.'],
                ]);
            }

            $lockedDocument->items()->findOrFail($item->id)->delete();
        });
    }
}
