<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteStopRequest;
use App\Http\Requests\UpdateRouteStopRequest;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RouteStopController extends Controller
{
    public function store(StoreRouteStopRequest $request, SalesRoute $salesRoute): RedirectResponse
    {
        Gate::authorize('update', $salesRoute);
        $salesRoute->stops()->create($request->validated());

        return redirect()->route('routes.show', $salesRoute)->with('success', 'Visita agregada a la ruta.');
    }

    public function update(
        UpdateRouteStopRequest $request,
        SalesRoute $salesRoute,
        RouteStop $stop,
    ): RedirectResponse {
        Gate::authorize('update', $salesRoute);
        $stop->update($request->validated());

        return redirect()->route('routes.show', $salesRoute)->with('success', 'Visita actualizada correctamente.');
    }

    public function destroy(SalesRoute $salesRoute, RouteStop $stop): RedirectResponse
    {
        Gate::authorize('update', $salesRoute);
        $stop->delete();

        return redirect()->route('routes.show', $salesRoute)->with('success', 'Visita retirada de la ruta.');
    }
}
