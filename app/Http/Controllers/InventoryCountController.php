<?php

namespace App\Http\Controllers;

use App\Actions\CreateInventoryCountAction;
use App\Actions\SaveInventoryCountItemsAction;
use App\Http\Requests\StoreInventoryCountRequest;
use App\Http\Requests\UpdateInventoryCountItemsRequest;
use App\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryCountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryCount::class);
        $status = $request->string('status')->toString();

        return view('inventory.counts.index', [
            'counts' => InventoryCount::query()
                ->with(['warehouse', 'creator'])
                ->withCount([
                    'items',
                    'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
                ])
                ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
                ->when(InventoryCountStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
                ->latest('counted_on')
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString(),
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'statuses' => InventoryCountStatus::cases(),
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryCount::class);

        return view('inventory.counts.create', [
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(
        StoreInventoryCountRequest $request,
        CreateInventoryCountAction $createCount,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $count = $createCount->handle($request->validated(), $user);

        return redirect()->route('inventory-counts.show', $count)
            ->with('success', 'Conteo iniciado con una fotografía de las existencias actuales.');
    }

    public function show(InventoryCount $inventoryCount): View
    {
        Gate::authorize('view', $inventoryCount);
        $inventoryCount->load([
            'warehouse',
            'creator',
            'postedBy',
            'cancelledBy',
            'items' => fn ($query) => $query->orderBy('product_name')->orderBy('id'),
        ]);

        return view('inventory.counts.show', ['count' => $inventoryCount]);
    }

    public function update(
        UpdateInventoryCountItemsRequest $request,
        InventoryCount $inventoryCount,
        SaveInventoryCountItemsAction $saveItems,
    ): RedirectResponse {
        $saveItems->handle($inventoryCount, $request->validated('items'));

        return redirect()->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Avance del conteo guardado.');
    }
}
