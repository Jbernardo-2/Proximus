<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesRouteRequest;
use App\Http\Requests\UpdateSalesRouteRequest;
use App\Models\Customer;
use App\Models\SalesRoute;
use App\Models\User;
use App\UserRole;
use App\Weekday;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesRouteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalesRoute::class);
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        return view('operations.routes.index', [
            'salesRoutes' => SalesRoute::query()
                ->when($user->role === UserRole::Preventista, fn ($query) => $query->where('salesperson_id', $user->id))
                ->with(['salesperson', 'driver'])
                ->withCount('stops')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('salesperson', fn ($person) => $person->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('driver', fn ($person) => $person->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalesRoute::class);

        return view('operations.routes.form', [
            'salesRoute' => new SalesRoute,
            'salespeople' => $this->personnel(UserRole::Preventista),
            'drivers' => $this->personnel(UserRole::Repartidor),
        ]);
    }

    public function store(StoreSalesRouteRequest $request): RedirectResponse
    {
        $salesRoute = SalesRoute::query()->create($request->validated());

        return redirect()->route('routes.show', $salesRoute)->with('success', 'Ruta creada correctamente.');
    }

    public function show(SalesRoute $salesRoute): View
    {
        Gate::authorize('view', $salesRoute);
        $salesRoute->load([
            'salesperson',
            'driver',
            'stops' => fn ($query) => $query
                ->with('customer')
                ->orderBy('visit_day')
                ->orderBy('visit_order')
                ->orderBy('id'),
        ]);

        return view('operations.routes.show', [
            'salesRoute' => $salesRoute,
            'hasActiveCustomers' => Customer::query()->active()->exists(),
            'weekdays' => Weekday::options(),
            'nextVisitOrder' => min(((int) $salesRoute->stops->max('visit_order')) + 1, 65535),
        ]);
    }

    public function edit(SalesRoute $salesRoute): View
    {
        Gate::authorize('update', $salesRoute);

        return view('operations.routes.form', [
            'salesRoute' => $salesRoute,
            'salespeople' => $this->personnel(UserRole::Preventista, $salesRoute->salesperson_id),
            'drivers' => $this->personnel(UserRole::Repartidor, $salesRoute->driver_id),
        ]);
    }

    public function update(UpdateSalesRouteRequest $request, SalesRoute $salesRoute): RedirectResponse
    {
        Gate::authorize('update', $salesRoute);
        $salesRoute->update($request->validated());

        return redirect()->route('routes.show', $salesRoute)->with('success', 'Ruta actualizada correctamente.');
    }

    public function destroy(SalesRoute $salesRoute): RedirectResponse
    {
        Gate::authorize('delete', $salesRoute);

        if ($salesRoute->stops()->exists()) {
            return back()->with('error', 'No se puede eliminar una ruta con visitas programadas. Puedes desactivarla o retirar primero sus visitas.');
        }

        $salesRoute->delete();

        return redirect()->route('routes.index')->with('success', 'Ruta eliminada correctamente.');
    }

    /** @return Collection<int, User> */
    private function personnel(UserRole $role, ?int $selectedId = null): Collection
    {
        return User::query()
            ->where('role', $role->value)
            ->where(function ($query) use ($selectedId): void {
                $query->where('is_active', true)
                    ->when($selectedId !== null, fn ($builder) => $builder->orWhereKey($selectedId));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }
}
