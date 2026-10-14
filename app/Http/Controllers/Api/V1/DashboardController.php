<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRun;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardService;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'role' => $user->role->value,
            ...match ($user->role) {
                UserRole::Admin, UserRole::Supervisor => ['management' => $this->management($dashboard->management())],
                UserRole::Repartidor => ['driver' => $this->driver($dashboard->forDriver($user))],
                default => ['panel' => $dashboard->forUser($user)],
            },
        ]]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function management(array $data): array
    {
        return [
            'today' => $data['today'],
            'receivables' => $data['receivables'],
            'pending' => collect($data['pending'])->map(fn (array $item, int $index): array => [
                'key' => ['unassigned_orders', 'runs_awaiting_settlement', 'stock_shortages', 'stock_to_replenish'][$index] ?? (string) $index,
                'label' => $item['label'],
                'count' => $item['count'],
                'critical' => $item['critical'],
            ])->values()->all(),
            'runs' => $data['runs']->map(fn (DeliveryRun $run): array => $this->run($run))->all(),
            'trend' => $data['trend'],
            'unassigned_orders' => $data['unassignedOrders']->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'total' => $order->total,
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            ])->all(),
            'overdue_receivables' => $data['overdueReceivables']->map(fn (CustomerReceivable $receivable): array => [
                'id' => $receivable->id,
                'customer_id' => $receivable->customer_id,
                'customer_name' => $receivable->customer?->business_name,
                'document_number' => $receivable->document_number,
                'outstanding_amount' => $receivable->outstanding_amount,
                'due_date' => $receivable->due_date?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function driver(array $data): array
    {
        $nextStop = $data['nextStop'];

        return [
            'metrics' => $data['metrics'],
            'achievements' => $data['achievements'],
            'current_run_id' => $data['currentRun']?->id,
            'next_stop' => $nextStop === null ? null : [
                'run_order_id' => $nextStop->id,
                'visit_order' => $nextStop->visit_order,
                'order_number' => $nextStop->order?->order_number,
                'customer_name' => $nextStop->order?->customer_name,
                'customer_address' => $nextStop->order?->customer_address,
            ],
            'runs' => $data['runs']->map(fn (DeliveryRun $run): array => $this->run($run))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function run(DeliveryRun $run): array
    {
        return [
            'id' => $run->id,
            'run_number' => $run->run_number,
            'driver_name' => $run->driver_name,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'scheduled_date' => $run->scheduled_date?->toDateString(),
            'stops' => (int) $run->stops_count,
            'completed_stops' => (int) $run->completed_stops_count,
            'delivered_total' => $run->delivered_total,
            'collected_total' => $run->collected_total,
            'credit_total' => $run->credit_total,
        ];
    }
}
