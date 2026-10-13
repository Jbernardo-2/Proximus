<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryOutcomeReason;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\Services\OperationalNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteDeliveryStopAction
{
    public function __construct(
        private RefreshDeliveryFinancialsAction $refreshFinancials,
        private OperationalNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{id: string, delivered_quantity: mixed, returned_quantity: mixed, damaged_quantity: mixed, missing_quantity: mixed}>  $items
     */
    public function handle(
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        array $data,
        array $items,
        User $actor,
    ): DeliveryRunOrder {
        $completedRunOrder = DB::transaction(function () use ($deliveryRun, $runOrder, $data, $items, $actor): DeliveryRunOrder {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! in_array($run->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true)) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La jornada no está disponible para registrar entregas.'],
                ]);
            }

            $lockedRunOrder = $run->runOrders()->with('order')->lockForUpdate()->findOrFail($runOrder->id);

            if ($lockedRunOrder->status !== DeliveryOrderStatus::Loaded) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['Esta parada ya tiene un resultado guardado.'],
                ]);
            }
            $runItems = DeliveryRunItem::query()
                ->with(['product', 'presentation'])
                ->where('delivery_run_order_id', $lockedRunOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $submitted = collect($items)->keyBy('id');

            if ($submitted->count() !== $runItems->count() || $runItems->contains(fn (DeliveryRunItem $item): bool => ! $submitted->has($item->id))) {
                throw ValidationException::withMessages([
                    'items' => ['Envía el resultado de cada producto cargado para este pedido.'],
                ]);
            }

            $deliveredTotal = '0.0000';
            $anyDelivered = false;
            $allRequestedDelivered = true;

            foreach ($runItems as $item) {
                $values = $submitted->get($item->id);
                $delivered = (string) $values['delivered_quantity'];
                $returned = (string) $values['returned_quantity'];
                $damaged = (string) $values['damaged_quantity'];
                $missing = (string) $values['missing_quantity'];

                foreach ([$delivered, $returned, $damaged, $missing] as $quantity) {
                    if (bccomp($quantity, '0', 6) < 0) {
                        throw ValidationException::withMessages([
                            'items' => ["Las cantidades de {$item->product_name} no pueden ser negativas."],
                        ]);
                    }

                    $allowsDecimal = $item->presentation->is_base && $item->product->allows_decimal;

                    if (! $allowsDecimal && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                        throw ValidationException::withMessages([
                            'items' => ["La presentación {$item->presentation_name} de {$item->product_name} requiere cantidades enteras."],
                        ]);
                    }
                }

                $accounted = bcadd(bcadd($delivered, $returned, 6), bcadd($damaged, $missing, 6), 6);

                if (bccomp($accounted, (string) $item->loaded_quantity, 6) !== 0) {
                    throw ValidationException::withMessages([
                        'items' => ["En {$item->product_name}, entregado + devuelto + dañado + faltante debe ser igual a lo cargado."],
                    ]);
                }

                $lineTotal = bcmul($delivered, (string) $item->unit_price, 4);
                $item->forceFill([
                    'delivered_quantity' => $delivered,
                    'delivered_base_quantity' => bcmul($delivered, (string) $item->conversion_factor, 6),
                    'returned_quantity' => $returned,
                    'returned_base_quantity' => bcmul($returned, (string) $item->conversion_factor, 6),
                    'damaged_quantity' => $damaged,
                    'damaged_base_quantity' => bcmul($damaged, (string) $item->conversion_factor, 6),
                    'missing_quantity' => $missing,
                    'missing_base_quantity' => bcmul($missing, (string) $item->conversion_factor, 6),
                    'delivered_line_total' => $lineTotal,
                ])->save();
                $deliveredTotal = bcadd($deliveredTotal, $lineTotal, 4);
                $anyDelivered = $anyDelivered || bccomp($delivered, '0', 6) > 0;
                $allRequestedDelivered = $allRequestedDelivered
                    && bccomp($delivered, (string) $item->requested_quantity, 6) === 0;
            }

            $outcomeStatus = $allRequestedDelivered
                ? DeliveryOrderStatus::Delivered
                : ($anyDelivered ? DeliveryOrderStatus::PartiallyDelivered : DeliveryOrderStatus::NotDelivered);
            $reason = DeliveryOutcomeReason::tryFrom((string) ($data['outcome_reason'] ?? ''));

            if ($outcomeStatus === DeliveryOrderStatus::Delivered) {
                $reason = null;
            } elseif ($reason === null) {
                throw ValidationException::withMessages([
                    'outcome_reason' => ['Selecciona el motivo de la entrega incompleta.'],
                ]);
            }

            if ($anyDelivered && empty($data['receiver_name'])) {
                throw ValidationException::withMessages([
                    'receiver_name' => ['Indica quién recibió la mercancía.'],
                ]);
            }

            $requiresSettlement = bccomp($deliveredTotal, '0', 4) > 0;
            $recordedAt = now();

            $lockedRunOrder->forceFill([
                'status' => $requiresSettlement ? DeliveryOrderStatus::AwaitingPayment : $outcomeStatus,
                'outcome_status' => $outcomeStatus,
                'delivered_total' => $deliveredTotal,
                'collected_total' => '0.0000',
                'balance_due' => $deliveredTotal,
                'outcome_reason' => $reason,
                'outcome_notes' => $data['outcome_notes'] ?? null,
                'outcome_recorded_at' => $recordedAt,
                'outcome_recorded_by' => $actor->id,
                'credit_reason' => null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'completed_at' => $requiresSettlement ? null : $recordedAt,
                'completed_by' => $requiresSettlement ? null : $actor->id,
            ])->save();

            if ($requiresSettlement) {
                $this->refreshFinancials->handle($run);

                return $lockedRunOrder->refresh();
            }

            $orderStatus = match ($outcomeStatus) {
                DeliveryOrderStatus::Delivered => OrderStatus::Delivered,
                DeliveryOrderStatus::PartiallyDelivered => OrderStatus::PartiallyDelivered,
                DeliveryOrderStatus::NotDelivered => OrderStatus::NotDelivered,
                default => throw new \LogicException('Estado de entrega inesperado.'),
            };
            $order = Order::query()->lockForUpdate()->findOrFail($lockedRunOrder->order_id);

            if ($order->status !== $orderStatus) {
                $previousStatus = $order->status;
                $order->forceFill(['status' => $orderStatus])->save();
                $order->statusHistory()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $orderStatus,
                    'changed_by' => $actor->id,
                    'reason' => $outcomeStatus->label()." en la jornada {$run->run_number}.".($reason ? ' '.$reason->label().'.' : ''),
                ]);
            }

            $allCompleted = $run->runOrders()
                ->whereNotIn('status', [
                    DeliveryOrderStatus::Delivered->value,
                    DeliveryOrderStatus::PartiallyDelivered->value,
                    DeliveryOrderStatus::NotDelivered->value,
                ])
                ->doesntExist();

            if ($allCompleted && $run->status !== DeliveryRunStatus::AwaitingSettlement) {
                $run->forceFill(['status' => DeliveryRunStatus::AwaitingSettlement])->save();
                $run->statusHistory()->create([
                    'from_status' => DeliveryRunStatus::InTransit,
                    'to_status' => DeliveryRunStatus::AwaitingSettlement,
                    'changed_by' => $actor->id,
                    'reason' => 'Todas las paradas tienen resultado y la jornada está lista para liquidación.',
                ]);
            }

            $this->refreshFinancials->handle($run);

            return $lockedRunOrder->refresh();
        });

        $this->notifications->deliveryOutcomeRecorded($completedRunOrder);

        return $completedRunOrder;
    }
}
