<?php

namespace App\Http\Controllers;

use App\Actions\SaveVehicleAction;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);
        $search = $request->string('search')->trim()->toString();

        return view('deliveries.vehicles.index', [
            'vehicles' => Vehicle::query()
                ->withCount('deliveryRuns')
                ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }))
                ->orderByDesc('is_active')
                ->orderBy('description')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('deliveries.vehicles.form', ['vehicle' => new Vehicle]);
    }

    public function store(StoreVehicleRequest $request, SaveVehicleAction $saveVehicle): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveVehicle->handle($request->validated(), $user);

        return redirect()->route('vehicles.index')->with('success', 'Vehículo registrado correctamente.');
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('deliveries.vehicles.form', ['vehicle' => $vehicle]);
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        SaveVehicleAction $saveVehicle,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $saveVehicle->handle($request->validated(), $user, $vehicle);

        return redirect()->route('vehicles.index')->with('success', 'Vehículo actualizado correctamente.');
    }
}
