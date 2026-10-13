<?php

namespace App\Http\Controllers;

use App\DeliveryRunStatus;
use App\Http\Requests\RouteReportIndexRequest;
use App\Models\DeliveryRun;
use App\Models\SalesRoute;
use App\Models\User;
use App\Services\RouteReportService;
use App\UserRole;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RouteReportController extends Controller
{
    public function index(RouteReportIndexRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $query = DeliveryRun::query()
            ->whereHas('runOrders.order', fn (Builder $orders): Builder => $orders->whereNotNull('sales_route_id'))
            ->when(
                $user->role === UserRole::Repartidor,
                fn (Builder $runs): Builder => $runs->where('driver_id', $user->id),
            )
            ->when(
                $user->role === UserRole::Preventista,
                fn (Builder $runs): Builder => $runs->whereHas(
                    'runOrders.order.salesRoute',
                    fn (Builder $routes): Builder => $routes->where('salesperson_id', $user->id),
                ),
            )
            ->when(
                isset($filters['date_from']),
                fn (Builder $runs): Builder => $runs->whereDate('scheduled_date', '>=', $filters['date_from']),
            )
            ->when(
                isset($filters['date_to']),
                fn (Builder $runs): Builder => $runs->whereDate('scheduled_date', '<=', $filters['date_to']),
            )
            ->when(
                isset($filters['driver_id']),
                fn (Builder $runs): Builder => $runs->where('driver_id', $filters['driver_id']),
            )
            ->when(
                isset($filters['status']),
                fn (Builder $runs): Builder => $runs->where('status', $filters['status']),
            )
            ->when(
                isset($filters['route_id']),
                fn (Builder $runs): Builder => $runs->whereHas(
                    'runOrders.order',
                    fn (Builder $orders): Builder => $orders->where('sales_route_id', $filters['route_id']),
                ),
            )
            ->when(
                isset($filters['salesperson_id']),
                fn (Builder $runs): Builder => $runs->whereHas(
                    'runOrders.order',
                    fn (Builder $orders): Builder => $orders->where('salesperson_id', $filters['salesperson_id']),
                ),
            )
            ->with([
                'driver',
                'vehicle',
                'warehouse',
                'runOrders.order.salesRoute.salesperson',
            ])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $reportsByRun = $query->getCollection()->mapWithKeys(function (DeliveryRun $deliveryRun) use ($filters, $user): array {
            $routes = $deliveryRun->runOrders
                ->groupBy('order.sales_route_id')
                ->map(function ($runOrders) use ($deliveryRun, $filters, $user): ?array {
                    $salesRoute = $runOrders->first()->order->salesRoute;

                    if ($salesRoute === null || Gate::forUser($user)->denies('viewRouteReport', [$deliveryRun, $salesRoute])) {
                        return null;
                    }

                    if (isset($filters['route_id']) && $salesRoute->id !== $filters['route_id']) {
                        return null;
                    }

                    if (isset($filters['salesperson_id']) && ! $runOrders->contains(
                        fn ($runOrder): bool => $runOrder->order->salesperson_id === (int) $filters['salesperson_id'],
                    )) {
                        return null;
                    }

                    return [
                        'route' => $salesRoute,
                        'orders_count' => $runOrders->count(),
                    ];
                })
                ->filter()
                ->sortBy(fn (array $report): string => $report['route']->name)
                ->values();

            return [$deliveryRun->id => $routes];
        });

        return view('reports.routes.index', [
            'deliveryRuns' => $query,
            'reportsByRun' => $reportsByRun,
            'filters' => $filters,
            'statuses' => DeliveryRunStatus::cases(),
            'salesRoutes' => $this->availableRoutes($user),
            'salespeople' => User::query()
                ->where('role', UserRole::Preventista)
                ->where('is_active', true)
                ->when($user->role === UserRole::Preventista, fn (Builder $users): Builder => $users->whereKey($user->id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'drivers' => User::query()
                ->where('role', UserRole::Repartidor)
                ->where('is_active', true)
                ->when($user->role === UserRole::Repartidor, fn (Builder $users): Builder => $users->whereKey($user->id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function print(
        DeliveryRun $deliveryRun,
        SalesRoute $salesRoute,
        RouteReportService $routeReportService,
    ): View {
        Gate::authorize('viewRouteReport', [$deliveryRun, $salesRoute]);

        return view('reports.routes.document', [
            ...$routeReportService->data($deliveryRun, $salesRoute),
            'isPdf' => false,
        ]);
    }

    public function download(
        DeliveryRun $deliveryRun,
        SalesRoute $salesRoute,
        RouteReportService $routeReportService,
    ): Response {
        Gate::authorize('viewRouteReport', [$deliveryRun, $salesRoute]);

        $html = view('reports.routes.document', [
            ...$routeReportService->data($deliveryRun, $salesRoute),
            'isPdf' => true,
        ])->render();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$routeReportService->filename($deliveryRun, $salesRoute).'"',
        ]);
    }

    /** @return Collection<int, SalesRoute> */
    private function availableRoutes(User $user): Collection
    {
        return SalesRoute::query()
            ->withTrashed()
            ->when(
                $user->role === UserRole::Preventista,
                fn (Builder $routes): Builder => $routes->where('salesperson_id', $user->id),
            )
            ->when(
                $user->role === UserRole::Repartidor,
                fn (Builder $routes): Builder => $routes->whereHas(
                    'orders.deliveryRunOrders.deliveryRun',
                    fn (Builder $runs): Builder => $runs->where('driver_id', $user->id),
                ),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
