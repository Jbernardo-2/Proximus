<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeasurementUnitRequest;
use App\Http\Requests\UpdateMeasurementUnitRequest;
use App\Http\Resources\Api\V1\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeasurementUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $units = MeasurementUnit::query()
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return MeasurementUnitResource::collection($units);
    }

    public function store(StoreMeasurementUnitRequest $request): JsonResponse
    {
        $unit = MeasurementUnit::query()->create($request->validated());

        return (new MeasurementUnitResource($unit->loadCount('products')))->response()->setStatusCode(201);
    }

    public function show(MeasurementUnit $measurementUnit): MeasurementUnitResource
    {
        return new MeasurementUnitResource($measurementUnit->loadCount('products'));
    }

    public function update(UpdateMeasurementUnitRequest $request, MeasurementUnit $measurementUnit): MeasurementUnitResource
    {
        $measurementUnit->update($request->validated());

        return new MeasurementUnitResource($measurementUnit->refresh()->loadCount('products'));
    }

    public function destroy(MeasurementUnit $measurementUnit): JsonResponse
    {
        if ($measurementUnit->products()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una unidad usada por productos.'], 422);
        }

        $measurementUnit->delete();

        return response()->json(null, 204);
    }
}
