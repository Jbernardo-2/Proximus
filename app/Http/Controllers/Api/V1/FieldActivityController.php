<?php

namespace App\Http\Controllers\Api\V1;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryRun;
use App\Models\User;
use App\Services\RouteActivityService;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** How the street goes today, for the supervisor: each salesperson's route and each run on the road. */
class FieldActivityController extends Controller
{
    public function index(RouteActivityService $activity): JsonResponse
    {
        $completed = collect(DeliveryOrderStatus::cases())->filter->isCompleted()->map->value->all();

        $salespeople = User::query()
            ->where('role', UserRole::Preventista)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (User $salesperson) use ($activity): ?array {
                $stops = collect($activity->map($activity->query($salesperson)->get(), $salesperson));

                if ($stops->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $salesperson->id,
                    'name' => $salesperson->name,
                    'stops' => $stops->count(),
                    'with_order' => $stops->where('has_order', true)->count(),
                    'no_sale' => $stops->where('status', 'no_sale')->count(),
                    'pending' => $stops->where('status', 'pending')->count(),
                    'sold' => $stops->reduce(fn (string $sum, array $stop): string => bcadd($sum, $stop['orders_total'], 4), '0.0000'),
                    'collected' => $stops->reduce(fn (string $sum, array $stop): string => bcadd($sum, $stop['payments_total'], 4), '0.0000'),
                    'last_activity_at' => $stops->pluck('activity_at')->filter()->sortDesc()->first(),
                ];
            })
            ->filter()
            ->values();

        $runs = DeliveryRun::query()
            ->whereIn('status', [DeliveryRunStatus::Loaded, DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement])
            ->withCount([
                'runOrders as stops_count',
                'runOrders as completed_stops_count' => fn (Builder $query) => $query->whereIn('status', $completed),
            ])
            ->withMax('runOrders as last_completed_at', 'completed_at')
            ->orderByRaw('case status when ? then 0 when ? then 1 else 2 end', [DeliveryRunStatus::InTransit->value, DeliveryRunStatus::AwaitingSettlement->value])
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (DeliveryRun $run): array => [
                'id' => $run->id,
                'run_number' => $run->run_number,
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'driver_name' => $run->driver_name,
                'stops' => (int) $run->stops_count,
                'completed_stops' => (int) $run->completed_stops_count,
                'delivered_total' => $run->delivered_total,
                'collected_total' => $run->collected_total,
                'departed_at' => $run->departed_at?->toIso8601String(),
                'last_completed_at' => $run->last_completed_at === null ? null : Carbon::parse($run->last_completed_at)->toIso8601String(),
            ]);

        return response()->json(['data' => ['salespeople' => $salespeople, 'runs' => $runs]]);
    }

    /** One salesperson's stops today, in visit order, as their phone sees them. */
    public function salesperson(User $user, RouteActivityService $activity): JsonResponse
    {
        abort_unless($user->role === UserRole::Preventista, 404);

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'stops' => $activity->map($activity->query($user)->get(), $user),
        ]]);
    }
}
