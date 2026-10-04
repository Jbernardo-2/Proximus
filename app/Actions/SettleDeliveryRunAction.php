<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\InventoryMovementType;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\User;
use App\PaymentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleDeliveryRunAction
{
    public function __construct(
        private ApplyInventoryDeltaAction $applyDelta,
        private RefreshDeliveryFinancialsAction $refreshFinancials,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(DeliveryRun $deliveryRun, array $data, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $data, $actor): DeliveryRun {
            $run = DeliveryRun::query()->with('warehouse')->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::AwaitingSettlement) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La jornada todavía no está lista para liquidación.'],
                ]);
            }

            $run = $this->refreshFinancials->handle($run)->load('warehouse');
            $runOrders = $run->runOrders()->with('order')->orderBy('visit_order')->lockForUpdate()->get();

            if ($runOrders->contains(fn ($runOrder): bool => ! $runOrder->status->isCompleted())) {
                throw ValidationException::withMessages([
                    'orders' => ['Todas las paradas deben tener un resultado antes de liquidar.'],
                ]);
            }

            foreach ($runOrders as $runOrder) {
                if ($runOrder->order->payment_term === PaymentTerm::Cash
                    && bccomp((string) $runOrder->balance_due, '0', 4) > 0
                    && blank($runOrder->credit_reason)) {
                    throw ValidationException::withMessages([
                        'credit' => ["El pedido {$runOrder->order->order_number} era de contado y quedó con saldo; registra una justificación de crédito."],
                    ]);
                }
            }

            $items = DeliveryRunItem::query()
                ->with(['product', 'deliveryRunOrder'])
                ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $run->id))
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $accounted = bcadd(
                    bcadd((string) $item->delivered_quantity, (string) $item->returned_quantity, 6),
                    bcadd((string) $item->damaged_quantity, (string) $item->missing_quantity, 6),
                    6,
                );

                if (bccomp($accounted, (string) $item->loaded_quantity, 6) !== 0) {
                    throw ValidationException::withMessages([
                        'items' => ["La mercancía en tránsito de {$item->product_name} todavía no está conciliada."],
                    ]);
                }

                if (bccomp((string) $item->returned_base_quantity, '0', 6) > 0) {
                    $this->applyDelta->handle(
                        $run->warehouse,
                        $item->product,
                        InventoryMovementType::DispatchReturn,
                        (string) $item->returned_base_quantity,
                        '0.000000',
                        $actor,
                        [
                            'product_presentation_id' => $item->product_presentation_id,
                            'order_id' => $item->deliveryRunOrder->order_id,
                            'order_item_id' => $item->order_item_id,
                            'delivery_run_id' => $run->id,
                            'delivery_run_order_id' => $item->delivery_run_order_id,
                            'delivery_run_item_id' => $item->id,
                            'presentation_quantity' => $item->returned_quantity,
                            'conversion_factor' => $item->conversion_factor,
                            'product_sku' => $item->product_sku,
                            'product_name' => $item->product_name,
                            'presentation_name' => $item->presentation_name,
                            'base_unit_symbol' => $item->base_unit_symbol,
                            'reference_number' => $run->run_number,
                            'reason' => 'Mercancía no entregada que regresó físicamente a bodega al liquidar la ruta.',
                        ],
                    );
                }
            }

            $cashDeclared = (string) $data['cash_declared'];
            $cashDifference = bcsub($cashDeclared, (string) $run->cash_expected, 4);

            if (bccomp($cashDifference, '0', 4) !== 0 && blank($data['settlement_notes'] ?? null)) {
                throw ValidationException::withMessages([
                    'settlement_notes' => ['Explica la diferencia entre el efectivo esperado y el declarado.'],
                ]);
            }

            $run->forceFill([
                'status' => DeliveryRunStatus::Settled,
                'cash_declared' => $cashDeclared,
                'cash_difference' => $cashDifference,
                'settlement_notes' => $data['settlement_notes'] ?? null,
                'settled_at' => now(),
                'settled_by' => $actor->id,
            ])->save();
            $run->statusHistory()->create([
                'from_status' => DeliveryRunStatus::AwaitingSettlement,
                'to_status' => DeliveryRunStatus::Settled,
                'changed_by' => $actor->id,
                'reason' => bccomp($cashDifference, '0', 4) === 0
                    ? 'Jornada liquidada y efectivo conciliado.'
                    : 'Jornada liquidada con diferencia documentada: '.($data['settlement_notes'] ?? ''),
            ]);

            return $run;
        }, 3);
    }
}
