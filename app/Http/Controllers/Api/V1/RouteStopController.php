<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteStopRequest;
use App\Http\Requests\UpdateRouteStopRequest;
use App\Http\Resources\Api\V1\RouteStopResource;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use Illuminate\Http\JsonResponse;

class RouteStopController extends Controller
{
    public function store(StoreRouteStopRequest $request, SalesRoute $salesRoute): JsonResponse
    {
        $stop = $salesRoute->stops()->create($request->validated());

        return (new RouteStopResource($stop->load('customer')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SalesRoute $salesRoute, RouteStop $stop): RouteStopResource
    {
        return new RouteStopResource($stop->load('customer'));
    }

    public function update(
        UpdateRouteStopRequest $request,
        SalesRoute $salesRoute,
        RouteStop $stop,
    ): RouteStopResource {
        $stop->update($request->validated());

        return new RouteStopResource($stop->refresh()->load('customer'));
    }

    public function destroy(SalesRoute $salesRoute, RouteStop $stop): JsonResponse
    {
        $stop->delete();

        return response()->json(null, 204);
    }
}
