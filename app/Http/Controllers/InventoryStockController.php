<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInventoryStockRequest;
use App\Models\InventoryStock;
use Illuminate\Http\RedirectResponse;

class InventoryStockController extends Controller
{
    public function update(UpdateInventoryStockRequest $request, InventoryStock $inventoryStock): RedirectResponse
    {
        $inventoryStock->update(['reorder_point' => $request->validated('reorder_point')]);

        return back()->with('success', 'Punto de reposición actualizado.');
    }
}
