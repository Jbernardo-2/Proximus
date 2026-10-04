<?php

namespace App\Http\Controllers;

use App\Actions\RemoveInventoryDocumentItemAction;
use App\Actions\SaveInventoryDocumentItemAction;
use App\Http\Requests\DeleteInventoryDocumentItemRequest;
use App\Http\Requests\StoreInventoryDocumentItemRequest;
use App\Http\Requests\UpdateInventoryDocumentItemRequest;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use Illuminate\Http\RedirectResponse;

class InventoryDocumentItemController extends Controller
{
    public function store(
        StoreInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        SaveInventoryDocumentItemAction $saveItem,
    ): RedirectResponse {
        $saveItem->handle($inventoryDocument, $request->validated());

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Producto agregado al movimiento.');
    }

    public function update(
        UpdateInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        InventoryDocumentItem $inventoryDocumentItem,
        SaveInventoryDocumentItemAction $saveItem,
    ): RedirectResponse {
        $saveItem->handle($inventoryDocument, $request->validated(), $inventoryDocumentItem);

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Línea actualizada.');
    }

    public function destroy(
        DeleteInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        InventoryDocumentItem $inventoryDocumentItem,
        RemoveInventoryDocumentItemAction $removeItem,
    ): RedirectResponse {
        $removeItem->handle($inventoryDocument, $inventoryDocumentItem);

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Producto retirado del movimiento.');
    }
}
