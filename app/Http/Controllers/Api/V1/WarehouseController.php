<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveWarehouseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Warehouse::class);

        return WarehouseResource::collection(Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get());
    }

    public function store(StoreWarehouseRequest $request, SaveWarehouseAction $saveWarehouse): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warehouse = $saveWarehouse->handle($request->validated(), $user);

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        Gate::authorize('view', $warehouse);

        return new WarehouseResource($warehouse);
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse,
        SaveWarehouseAction $saveWarehouse,
    ): WarehouseResource {
        /** @var User $user */
        $user = $request->user();

        return new WarehouseResource($saveWarehouse->handle($request->validated(), $user, $warehouse));
    }
}
