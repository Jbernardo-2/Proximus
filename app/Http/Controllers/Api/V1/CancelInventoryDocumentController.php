<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelInventoryDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInventoryRequest;
use App\Http\Resources\Api\V1\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Models\User;

class CancelInventoryDocumentController extends Controller
{
    public function __invoke(
        CancelInventoryRequest $request,
        InventoryDocument $inventoryDocument,
        CancelInventoryDocumentAction $cancelDocument,
    ): InventoryDocumentResource {
        /** @var User $user */
        $user = $request->user();
        $document = $cancelDocument->handle($inventoryDocument, $user, $request->validated('reason'));

        return new InventoryDocumentResource($document->load(['warehouse', 'supplier', 'creator', 'cancelledBy', 'items'])->loadCount('items'));
    }
}
