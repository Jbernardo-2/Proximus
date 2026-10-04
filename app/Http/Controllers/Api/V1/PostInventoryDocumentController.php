<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PostInventoryDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostInventoryDocumentRequest;
use App\Http\Resources\Api\V1\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Models\User;

class PostInventoryDocumentController extends Controller
{
    public function __invoke(
        PostInventoryDocumentRequest $request,
        InventoryDocument $inventoryDocument,
        PostInventoryDocumentAction $postDocument,
    ): InventoryDocumentResource {
        /** @var User $user */
        $user = $request->user();
        $document = $postDocument->handle($inventoryDocument, $user);

        return new InventoryDocumentResource($document->load(['warehouse', 'supplier', 'creator', 'postedBy', 'items'])->loadCount('items'));
    }
}
