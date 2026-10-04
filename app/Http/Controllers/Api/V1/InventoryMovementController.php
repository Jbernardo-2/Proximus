<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\InventoryMovementType;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryMovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('operate-inventory');
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->toString();

        return InventoryMovementResource::collection(InventoryMovement::query()
            ->with(['warehouse', 'creator'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when(InventoryMovementType::tryFrom($type) !== null, fn ($query) => $query->where('type', $type))
            ->when($search !== '', fn ($query) => $query->where(fn ($builder) => $builder
                ->where('product_name', 'like', "%{$search}%")
                ->orWhere('product_sku', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhere('lot_number', 'like', "%{$search}%")))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100)));
    }

    public function show(InventoryMovement $inventoryMovement): InventoryMovementResource
    {
        Gate::authorize('operate-inventory');

        return new InventoryMovementResource($inventoryMovement->load(['warehouse', 'creator']));
    }
}
