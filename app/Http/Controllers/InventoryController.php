<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', InventoryStock::class);
        $warehouses = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get();
        $selectedWarehouse = $request->filled('warehouse_id')
            ? $warehouses->firstWhere('id', $request->string('warehouse_id')->toString())
            : ($warehouses->firstWhere('is_default', true) ?? $warehouses->first());
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $stockQuery = InventoryStock::query()
            ->with(['warehouse', 'product.baseUnit', 'product.category'])
            ->when($selectedWarehouse !== null, fn ($query) => $query->where('warehouse_id', $selectedWarehouse->id))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('product', function ($products) use ($search): void {
                    $products->withTrashed()->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                });
            })
            ->when($status === 'shortage', fn ($query) => $query->whereColumn('quantity_on_hand', '<', 'quantity_reserved'))
            ->when($status === 'low', fn ($query) => $query
                ->whereColumn('quantity_on_hand', '>=', 'quantity_reserved')
                ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point'))
            ->when($status === 'available', fn ($query) => $query->whereRaw('(quantity_on_hand - quantity_reserved) > reorder_point'))
            ->when($status === 'zero', fn ($query) => $query
                ->where('quantity_on_hand', 0)
                ->where('quantity_reserved', 0));
        $metricsQuery = InventoryStock::query()
            ->when($selectedWarehouse !== null, fn ($query) => $query->where('warehouse_id', $selectedWarehouse->id));

        return view('inventory.index', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'search' => $search,
            'selectedStatus' => $status,
            'stocks' => $stockQuery
                ->join('products', 'products.id', '=', 'inventory_stocks.product_id')
                ->orderBy('products.name')
                ->select('inventory_stocks.*')
                ->paginate(25)
                ->withQueryString(),
            'metrics' => [
                'products' => (clone $metricsQuery)->count(),
                'with_stock' => (clone $metricsQuery)->where('quantity_on_hand', '>', 0)->count(),
                'shortages' => (clone $metricsQuery)->whereColumn('quantity_on_hand', '<', 'quantity_reserved')->count(),
                'low_stock' => (clone $metricsQuery)
                    ->whereColumn('quantity_on_hand', '>=', 'quantity_reserved')
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point')
                    ->count(),
            ],
            'recentMovements' => $request->user()?->canOperateInventory()
                ? InventoryMovement::query()
                    ->with(['warehouse', 'creator'])
                    ->when($selectedWarehouse !== null, fn ($query) => $query->where('warehouse_id', $selectedWarehouse->id))
                    ->latest('occurred_at')
                    ->latest('id')
                    ->limit(8)
                    ->get()
                : collect(),
        ]);
    }
}
