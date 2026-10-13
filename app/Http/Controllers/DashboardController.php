<?php

namespace App\Http\Controllers;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\User;
use App\Services\DashboardService;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('dashboards.index', [
            'driverDashboard' => $user->role === UserRole::Repartidor
                ? $this->driverDashboard($user)
                : null,
            'managementDashboard' => in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true)
                ? $dashboardService->management()
                : null,
            'roleDashboard' => in_array($user->role, [UserRole::Bodeguero, UserRole::Preventista], true)
                ? $dashboardService->forUser($user)
                : null,
        ]);
    }

    /**
     * @return array{
     *     runs: Collection<int, DeliveryRun>,
     *     currentRun: ?DeliveryRun,
     *     nextStop: mixed,
     *     metrics: array{stops: int, completed: int, pending: int, progress: int, delivered: string, collected: string, credit: string},
     *     achievements: list<array{label: string, description: string, earned: bool}>
     * }
     */
    private function driverDashboard(User $user): array
    {
        $completedStatuses = [
            DeliveryOrderStatus::Delivered->value,
            DeliveryOrderStatus::PartiallyDelivered->value,
            DeliveryOrderStatus::NotDelivered->value,
        ];
        $openStatuses = collect(DeliveryRunStatus::cases())
            ->filter(fn (DeliveryRunStatus $status): bool => $status->isOpen())
            ->map->value
            ->all();
        $runs = DeliveryRun::query()
            ->visibleTo($user)
            ->where(function ($query) use ($openStatuses): void {
                $query->whereIn('status', $openStatuses)
                    ->orWhere(function ($today): void {
                        $today->where('status', DeliveryRunStatus::Settled)
                            ->whereDate('scheduled_date', today());
                    });
            })
            ->withCount([
                'runOrders as stops_count',
                'runOrders as completed_stops_count' => fn ($query) => $query->whereIn('status', $completedStatuses),
                'runOrders as delivered_stops_count' => fn ($query) => $query->where('status', DeliveryOrderStatus::Delivered),
            ])
            ->orderByRaw('case status when ? then 0 when ? then 1 when ? then 2 when ? then 3 else 4 end', [
                DeliveryRunStatus::InTransit->value,
                DeliveryRunStatus::Loaded->value,
                DeliveryRunStatus::AwaitingSettlement->value,
                DeliveryRunStatus::Preparing->value,
            ])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
        $currentRun = $runs->first();
        $nextStop = $currentRun?->runOrders()
            ->whereNotIn('status', $completedStatuses)
            ->with('order:id,order_number,customer_name,customer_address')
            ->orderBy('visit_order')
            ->orderBy('id')
            ->first();
        $stops = (int) $runs->sum('stops_count');
        $completed = (int) $runs->sum('completed_stops_count');
        $pending = max($stops - $completed, 0);
        $delivered = $this->sumMoney($runs, 'delivered_total');
        $collected = $this->sumMoney($runs, 'collected_total');
        $credit = $this->sumMoney($runs, 'credit_total');
        $progress = $stops > 0 ? (int) round(($completed / $stops) * 100) : 0;

        return [
            'runs' => $runs,
            'currentRun' => $currentRun,
            'nextStop' => $nextStop,
            'metrics' => [
                'stops' => $stops,
                'completed' => $completed,
                'pending' => $pending,
                'progress' => $progress,
                'delivered' => $delivered,
                'collected' => $collected,
                'credit' => $credit,
            ],
            'achievements' => [
                ['label' => 'Primera entrega', 'description' => 'Completaste al menos una parada.', 'earned' => $completed >= 1],
                ['label' => 'Mitad de la ruta', 'description' => 'Alcanzaste el 50% de las paradas.', 'earned' => $stops > 0 && $completed * 2 >= $stops],
                ['label' => 'Cobranza al día', 'description' => 'No tienes saldo pendiente en las jornadas visibles.', 'earned' => $completed > 0 && bccomp($credit, '0', 4) === 0],
                ['label' => 'Ruta completada', 'description' => 'Terminaste todas las paradas asignadas.', 'earned' => $stops > 0 && $pending === 0],
            ],
        ];
    }

    /** @param Collection<int, DeliveryRun> $runs */
    private function sumMoney(Collection $runs, string $attribute): string
    {
        return $runs->reduce(
            fn (string $total, DeliveryRun $run): string => bcadd($total, (string) $run->getAttribute($attribute), 4),
            '0.0000',
        );
    }
}
