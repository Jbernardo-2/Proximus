<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesRouteRequest;
use App\Http\Requests\UpdateSalesRouteRequest;
use App\Http\Resources\Api\V1\SalesRouteResource;
use App\Models\SalesRoute;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SalesRouteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SalesRoute::class);
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $salespersonId = $request->integer('salesperson_id');
        $driverId = $request->integer('driver_id');
        $salesRoutes = SalesRoute::query()
            ->when($user->role === UserRole::Preventista, fn ($query) => $query->where('salesperson_id', $user->id))
            ->with(['salesperson', 'driver'])
            ->withCount('stops')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(
                $salespersonId > 0 && $user->role !== UserRole::Preventista,
                fn ($query) => $query->where('salesperson_id', $salespersonId),
            )
            ->when($driverId > 0, fn ($query) => $query->where('driver_id', $driverId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return SalesRouteResource::collection($salesRoutes);
    }

    public function store(StoreSalesRouteRequest $request): JsonResponse
    {
        $salesRoute = SalesRoute::query()->create($request->validated());

        return (new SalesRouteResource($salesRoute->load(['salesperson', 'driver'])->loadCount('stops')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SalesRoute $salesRoute): SalesRouteResource
    {
        Gate::authorize('view', $salesRoute);

        return new SalesRouteResource($salesRoute->load([
            'salesperson',
            'driver',
            'stops' => fn ($query) => $query
                ->with('customer')
                ->orderBy('visit_day')
                ->orderBy('visit_order')
                ->orderBy('id'),
        ])->loadCount('stops'));
    }

    public function update(UpdateSalesRouteRequest $request, SalesRoute $salesRoute): SalesRouteResource
    {
        Gate::authorize('update', $salesRoute);
        $salesRoute->update($request->validated());

        return new SalesRouteResource($salesRoute->refresh()->load(['salesperson', 'driver'])->loadCount('stops'));
    }

    public function destroy(SalesRoute $salesRoute): JsonResponse
    {
        Gate::authorize('delete', $salesRoute);

        if ($salesRoute->stops()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una ruta con visitas programadas.',
            ], 422);
        }

        $salesRoute->delete();

        return response()->json(null, 204);
    }
}
