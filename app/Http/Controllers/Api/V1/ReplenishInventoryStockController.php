<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReplenishInventoryStockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplenishInventoryStockRequest;
use App\Http\Resources\Api\V1\InventoryStockResource;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ReplenishInventoryStockController extends Controller
{
    public function __invoke(
        ReplenishInventoryStockRequest $request,
        InventoryStock $inventoryStock,
        ReplenishInventoryStockAction $replenishStock,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $stock = $replenishStock->handle($inventoryStock, (string) $request->validated('quantity'), $user);

        return (new InventoryStockResource($stock->load(['warehouse', 'product.baseUnit'])))->response()->setStatusCode(201);
    }
}
