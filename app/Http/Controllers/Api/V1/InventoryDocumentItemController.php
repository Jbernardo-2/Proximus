<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RemoveInventoryDocumentItemAction;
use App\Actions\SaveInventoryDocumentItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteInventoryDocumentItemRequest;
use App\Http\Requests\StoreInventoryDocumentItemRequest;
use App\Http\Requests\UpdateInventoryDocumentItemRequest;
use App\Http\Resources\Api\V1\InventoryDocumentItemResource;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use Illuminate\Http\JsonResponse;

class InventoryDocumentItemController extends Controller
{
    public function store(
        StoreInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        SaveInventoryDocumentItemAction $saveItem,
    ): JsonResponse {
        $item = $saveItem->handle($inventoryDocument, $request->validated());

        return (new InventoryDocumentItemResource($item))->response()->setStatusCode(201);
    }

    public function update(
        UpdateInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        InventoryDocumentItem $inventoryDocumentItem,
        SaveInventoryDocumentItemAction $saveItem,
    ): InventoryDocumentItemResource {
        return new InventoryDocumentItemResource(
            $saveItem->handle($inventoryDocument, $request->validated(), $inventoryDocumentItem),
        );
    }

    public function destroy(
        DeleteInventoryDocumentItemRequest $request,
        InventoryDocument $inventoryDocument,
        InventoryDocumentItem $inventoryDocumentItem,
        RemoveInventoryDocumentItemAction $removeItem,
    ): JsonResponse {
        $removeItem->handle($inventoryDocument, $inventoryDocumentItem);

        return response()->json(null, 204);
    }
}
