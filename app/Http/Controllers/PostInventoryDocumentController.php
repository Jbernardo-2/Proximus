<?php

namespace App\Http\Controllers;

use App\Actions\PostInventoryDocumentAction;
use App\Http\Requests\PostInventoryDocumentRequest;
use App\Models\InventoryDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostInventoryDocumentController extends Controller
{
    public function __invoke(
        PostInventoryDocumentRequest $request,
        InventoryDocument $inventoryDocument,
        PostInventoryDocumentAction $postDocument,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $postDocument->handle($inventoryDocument, $user);

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Movimiento aplicado. Las existencias fueron actualizadas.');
    }
}
