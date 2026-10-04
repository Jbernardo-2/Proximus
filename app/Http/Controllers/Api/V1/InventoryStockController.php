<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInventoryStockRequest;
use App\Http\Resources\Api\V1\InventoryStockResource;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryStockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryStock::class);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $stocks = InventoryStock::query()
            ->with(['warehouse', 'product.baseUnit'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when($search !== '', fn ($query) => $query->whereHas('product', fn ($products) => $products
                ->withTrashed()
                ->where(fn ($builder) => $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))))
            ->when($status === 'shortage', fn ($query) => $query->whereColumn('quantity_on_hand', '<', 'quantity_reserved'))
            ->when($status === 'low', fn ($query) => $query
                ->whereColumn('quantity_on_hand', '>=', 'quantity_reserved')
                ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point'))
            ->join('products', 'products.id', '=', 'inventory_stocks.product_id')
            ->orderBy('products.name')
            ->select('inventory_stocks.*')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return InventoryStockResource::collection($stocks);
    }

    public function show(InventoryStock $inventoryStock): InventoryStockResource
    {
        Gate::authorize('view', $inventoryStock);

        return new InventoryStockResource($inventoryStock->load(['warehouse', 'product.baseUnit']));
    }

    public function update(
        UpdateInventoryStockRequest $request,
        InventoryStock $inventoryStock,
    ): InventoryStockResource {
        $inventoryStock->update(['reorder_point' => $request->validated('reorder_point')]);

        return new InventoryStockResource($inventoryStock->refresh()->load(['warehouse', 'product.baseUnit']));
    }
}
