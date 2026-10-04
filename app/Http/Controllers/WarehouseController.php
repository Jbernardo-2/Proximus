<?php

namespace App\Http\Controllers;

use App\Actions\SaveWarehouseAction;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        return view('inventory.warehouses.index', [
            'warehouses' => Warehouse::query()->withCount(['stocks', 'orders'])->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        return view('inventory.warehouses.form', ['warehouse' => new Warehouse]);
    }

    public function store(StoreWarehouseRequest $request, SaveWarehouseAction $saveWarehouse): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warehouse = $saveWarehouse->handle($request->validated(), $user);

        return redirect()->route('warehouses.index')->with('success', "Bodega {$warehouse->name} creada.");
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('inventory.warehouses.form', ['warehouse' => $warehouse]);
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse,
        SaveWarehouseAction $saveWarehouse,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $saveWarehouse->handle($request->validated(), $user, $warehouse);

        return redirect()->route('warehouses.index')->with('success', 'Bodega actualizada correctamente.');
    }
}
