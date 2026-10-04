<?php

namespace App\Http\Controllers;

use App\Actions\CancelInventoryCountAction;
use App\Http\Requests\CancelInventoryRequest;
use App\Models\InventoryCount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CancelInventoryCountController extends Controller
{
    public function __invoke(
        CancelInventoryRequest $request,
        InventoryCount $inventoryCount,
        CancelInventoryCountAction $cancelCount,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $cancelCount->handle($inventoryCount, $user, $request->validated('reason'));

        return redirect()->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Conteo cancelado sin modificar existencias.');
    }
}
