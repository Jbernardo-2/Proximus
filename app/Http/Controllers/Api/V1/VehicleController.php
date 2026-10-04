<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Vehicle::class);

        return VehicleResource::collection(Vehicle::query()
            ->orderByDesc('is_active')
            ->orderBy('description')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function store(StoreVehicleRequest $request, SaveVehicleAction $saveVehicle): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new VehicleResource($saveVehicle->handle($request->validated(), $user)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        SaveVehicleAction $saveVehicle,
    ): VehicleResource {
        /** @var User $user */
        $user = $request->user();

        return new VehicleResource($saveVehicle->handle($request->validated(), $user, $vehicle));
    }
}
