<?php

namespace App\Http\Controllers;

use App\Actions\ReplenishInventoryStockAction;
use App\Http\Requests\ReplenishInventoryStockRequest;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ReplenishInventoryStockController extends Controller
{
    public function __invoke(
        ReplenishInventoryStockRequest $request,
        InventoryStock $inventoryStock,
        ReplenishInventoryStockAction $replenishStock,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $replenishStock->handle($inventoryStock, (string) $request->validated('quantity'), $user);

        return back()->with('success', 'Entrada registrada. El inventario físico y disponible fue actualizado.');
    }
}
