<?php

namespace App\Services;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\InventoryCount;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class OperationalNotificationService
{
    public function orderConfirmed(Order $order): void
    {
        $this->notifyRoles(
            [UserRole::Admin, UserRole::Supervisor, UserRole::Bodeguero],
            new OperationalNotification([
                'type' => 'order_confirmed',
                'title' => 'Nuevo pedido para bodega',
                'message' => "{$order->order_number} de {$order->customer_name} fue confirmado y espera asignación.",
                'severity' => 'important',
                'url' => route('orders.show', $order, false),
            ]),
        );
    }

    public function deliveryRunLoaded(DeliveryRun $deliveryRun): void
    {
        $driver = $deliveryRun->driver()->where('is_active', true)->first();

        $driver?->notify(new OperationalNotification([
            'type' => 'delivery_run_loaded',
            'title' => 'Tu jornada está cargada',
            'message' => "La jornada {$deliveryRun->run_number} está lista para iniciar ruta.",
            'severity' => 'important',
            'url' => route('delivery-runs.show', $deliveryRun, false),
        ]));
    }

    public function deliveryOutcomeRecorded(DeliveryRunOrder $runOrder): void
    {
        $runOrder->loadMissing(['order.salesperson', 'deliveryRun']);
        $outcome = $runOrder->outcome_status;

        if (! $outcome instanceof DeliveryOrderStatus) {
            return;
        }

        $notification = new OperationalNotification([
            'type' => 'delivery_outcome',
            'title' => $outcome === DeliveryOrderStatus::Delivered ? 'Pedido entregado' : 'Entrega con novedad',
            'message' => "{$runOrder->order->order_number} · {$outcome->label()} en {$runOrder->deliveryRun->run_number}.",
            'severity' => $outcome === DeliveryOrderStatus::Delivered ? 'success' : 'warning',
            'url' => route('orders.show', $runOrder->order, false),
        ]);
        $salesperson = $runOrder->order->salesperson;

        if ($salesperson?->is_active) {
            $salesperson->notify($notification);
        }

        if ($outcome !== DeliveryOrderStatus::Delivered) {
            $this->notifyRoles([UserRole::Admin, UserRole::Supervisor], $notification);
        }

        if ($runOrder->deliveryRun->status === DeliveryRunStatus::AwaitingSettlement) {
            $this->deliveryRunReadyForSettlement($runOrder->deliveryRun);
        }
    }

    public function deliveryRunReadyForSettlement(DeliveryRun $deliveryRun): void
    {
        $this->notifyRoles(
            [UserRole::Admin, UserRole::Supervisor],
            new OperationalNotification([
                'type' => 'delivery_run_awaiting_settlement',
                'title' => 'Jornada lista para liquidar',
                'message' => "{$deliveryRun->run_number} terminó sus paradas y requiere liquidación.",
                'severity' => 'important',
                'url' => route('delivery-runs.show', $deliveryRun, false),
            ]),
        );
    }

    /** A count is applied right away; the supervisor hears about every difference. */
    public function productCountAdjusted(InventoryCount $count, User $actor): void
    {
        $item = $count->items->first();

        if ($item === null || bccomp((string) $item->difference, '0', 6) === 0) {
            return;
        }

        $difference = rtrim(rtrim((string) $item->difference, '0'), '.');
        $this->notifyRoles(
            [UserRole::Admin, UserRole::Supervisor],
            new OperationalNotification([
                'type' => 'product_count_adjusted',
                'title' => 'Ajuste por conteo',
                'message' => "{$actor->name} contó {$item->product_name} en {$count->warehouse->name}: diferencia de {$difference} {$item->base_unit_symbol}.",
                'severity' => 'important',
                'url' => route('inventory-counts.show', $count, false),
            ]),
        );
    }

    /** @param list<UserRole> $roles */
    private function notifyRoles(array $roles, OperationalNotification $notification): void
    {
        User::query()
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles))
            ->orderBy('id')
            ->chunkById(100, fn ($users) => Notification::send($users, $notification));
    }
}
