<?php

namespace App\Http\Controllers;

use App\Actions\PostInventoryCountAction;
use App\Http\Requests\PostInventoryCountRequest;
use App\Models\InventoryCount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostInventoryCountController extends Controller
{
    public function __invoke(
        PostInventoryCountRequest $request,
        InventoryCount $inventoryCount,
        PostInventoryCountAction $postCount,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $postCount->handle($inventoryCount, $user);

        return redirect()->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Conteo aplicado y diferencias registradas en la bitácora.');
    }
}
