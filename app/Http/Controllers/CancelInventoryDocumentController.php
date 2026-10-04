<?php

namespace App\Http\Controllers;

use App\Actions\CancelInventoryDocumentAction;
use App\Http\Requests\CancelInventoryRequest;
use App\Models\InventoryDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CancelInventoryDocumentController extends Controller
{
    public function __invoke(
        CancelInventoryRequest $request,
        InventoryDocument $inventoryDocument,
        CancelInventoryDocumentAction $cancelDocument,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $cancelDocument->handle($inventoryDocument, $user, $request->validated('reason'));

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Movimiento borrador cancelado sin afectar existencias.');
    }
}
