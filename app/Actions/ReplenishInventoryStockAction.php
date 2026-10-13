<?php

namespace App\Actions;

use App\InventoryDocumentType;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishInventoryStockAction
{
    public function __construct(
        private CreateInventoryDocumentAction $createDocument,
        private SaveInventoryDocumentItemAction $saveDocumentItem,
        private PostInventoryDocumentAction $postDocument,
    ) {}

    public function handle(InventoryStock $inventoryStock, string $quantity, User $actor): InventoryStock
    {
        return DB::transaction(function () use ($inventoryStock, $quantity, $actor): InventoryStock {
            $inventoryStock->loadMissing(['warehouse', 'product.basePresentation']);
            $presentation = $inventoryStock->product->basePresentation;

            if ($presentation === null) {
                throw ValidationException::withMessages([
                    'quantity' => ['El producto no tiene una presentación base disponible.'],
                ]);
            }

            $document = $this->createDocument->handle([
                'warehouse_id' => $inventoryStock->warehouse_id,
                'supplier_id' => null,
                'type' => InventoryDocumentType::Receipt->value,
                'occurred_on' => today()->toDateString(),
                'external_reference' => null,
                'notes' => 'Reposición rápida desde existencias.',
            ], $actor);

            $this->saveDocumentItem->handle($document, [
                'product_presentation_id' => $presentation->id,
                'quantity' => $quantity,
                'unit_cost' => null,
                'lot_number' => null,
                'expiration_date' => null,
                'notes' => null,
            ]);

            $this->postDocument->handle($document, $actor);

            return InventoryStock::query()->findOrFail($inventoryStock->id);
        }, 3);
    }
}
