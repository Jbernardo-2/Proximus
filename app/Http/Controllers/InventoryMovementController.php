<?php

namespace App\Http\Controllers;

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('operate-inventory');
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->toString();

        return view('inventory.movements.index', [
            'movements' => InventoryMovement::query()
                ->with(['warehouse', 'creator'])
                ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
                ->when(InventoryMovementType::tryFrom($type) !== null, fn ($query) => $query->where('type', $type))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('product_name', 'like', "%{$search}%")
                            ->orWhere('product_sku', 'like', "%{$search}%")
                            ->orWhere('reference_number', 'like', "%{$search}%")
                            ->orWhere('lot_number', 'like', "%{$search}%");
                    });
                })
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'types' => InventoryMovementType::cases(),
            'search' => $search,
            'selectedType' => $type,
        ]);
    }
}
