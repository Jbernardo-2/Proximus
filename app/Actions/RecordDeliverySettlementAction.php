<?php

namespace App\Actions;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerCreditAuthorizationType;
use App\CustomerReceivableSource;
use App\CustomerReceivableStatus;
use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\DeliverySettlement;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\Services\CustomerCreditService;
use App\Services\OperationalNotificationService;
use App\SettlementMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDeliverySettlementAction
{
    public function __construct(
        private GenerateDeliveryNumberAction $generateNumber,
        private RefreshDeliveryFinancialsAction $refreshFinancials,
        private CustomerCreditService $customerCredit,
        private OperationalNotificationService $notifications,
    ) {}

    /**
     * @param  array{allocations: list<array{method: string, amount: mixed, reference?: ?string}>, credit_reason?: ?string, credit_override_reason?: ?string}  $data
     */
    public function handle(
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        array $data,
        User $actor,
    ): DeliverySettlement {
        $settlement = DB::transaction(function () use ($deliveryRun, $runOrder, $data, $actor): DeliverySettlement {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! in_array($run->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true)) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La jornada no acepta cierres de entrega en su estado actual.'],
                ]);
            }

            $lockedRunOrder = $run->runOrders()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($runOrder->id);

            if ($lockedRunOrder->status !== DeliveryOrderStatus::AwaitingPayment) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['Esta parada no está pendiente de cobro.'],
                ]);
            }

            if ($lockedRunOrder->settlement()->exists()) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['La entrega ya tiene un cierre financiero registrado.'],
                ]);
            }

            $allocationTotal = collect($data['allocations'])->reduce(
                fn (string $total, array $allocation): string => bcadd($total, (string) $allocation['amount'], 4),
                '0.0000',
            );

            if (bccomp($allocationTotal, (string) $lockedRunOrder->delivered_total, 4) !== 0) {
                throw ValidationException::withMessages([
                    'allocations' => ['La distribución debe sumar exactamente el total entregado.'],
                ]);
            }

            $settledAt = now();
            $order = Order::query()->lockForUpdate()->findOrFail($lockedRunOrder->order_id);
            $settlement = $lockedRunOrder->settlement()->create([
                'invoice_number' => $this->generateNumber->handle('invoice', 'FAC', $settledAt),
                'total_amount' => $allocationTotal,
                'currency' => $order->currency,
                'settled_at' => $settledAt,
                'settled_by' => $actor->id,
            ]);

            CustomerAccountEntry::query()->create([
                'customer_id' => $order->customer_id,
                'type' => CustomerAccountEntryType::Sale,
                'status' => CustomerAccountEntryStatus::Active,
                'order_id' => $order->id,
                'delivery_run_order_id' => $lockedRunOrder->id,
                'debit_amount' => $allocationTotal,
                'credit_amount' => '0.0000',
                'currency' => $order->currency,
                'notes' => "Venta entregada en {$run->run_number}.",
                'occurred_at' => $settledAt,
                'created_by' => $actor->id,
            ]);

            $collectedTotal = '0.0000';
            $creditTotal = '0.0000';

            foreach ($data['allocations'] as $allocation) {
                $method = SettlementMethod::from($allocation['method']);
                $amount = (string) $allocation['amount'];
                $reference = $allocation['reference'] ?? null;

                if ($method->isCredit()) {
                    if (bccomp($creditTotal, '0', 4) > 0) {
                        throw ValidationException::withMessages([
                            'allocations' => ['Agrega el crédito en una sola línea.'],
                        ]);
                    }

                    if (blank($data['credit_reason'] ?? null)) {
                        throw ValidationException::withMessages([
                            'credit_reason' => ['Describe el acuerdo o motivo del crédito.'],
                        ]);
                    }

                    $customer = Customer::query()->lockForUpdate()->findOrFail($order->customer_id);
                    $this->customerCredit->authorize(
                        customer: $customer,
                        amount: $amount,
                        actor: $actor,
                        type: CustomerCreditAuthorizationType::DeliverySettlement,
                        order: $order,
                        runOrder: $lockedRunOrder,
                        overrideReason: $data['credit_override_reason'] ?? null,
                    );
                    $profile = $customer->creditProfile()->firstOrFail();
                    $termDays = $profile->term_days + $profile->grace_days;

                    $receivable = CustomerReceivable::query()->create([
                        'customer_id' => $order->customer_id,
                        'source' => CustomerReceivableSource::Sale,
                        'order_id' => $order->id,
                        'delivery_run_order_id' => $lockedRunOrder->id,
                        'document_number' => $order->order_number,
                        'original_amount' => $amount,
                        'outstanding_amount' => $amount,
                        'currency' => $order->currency,
                        'status' => CustomerReceivableStatus::Open,
                        'opened_at' => $settledAt,
                        'issued_at' => $settledAt->toDateString(),
                        'due_date' => $settledAt->copy()->addDays($termDays)->toDateString(),
                        'term_days' => $termDays,
                        'notes' => $data['credit_reason'],
                        'created_by' => $actor->id,
                    ]);
                    $settlement->lines()->create([
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => null,
                        'customer_receivable_id' => $receivable->id,
                    ]);
                    $creditTotal = $amount;

                    continue;
                }

                $paymentMethod = $method->paymentMethod();

                if ($method->requiresReference() && blank($reference)) {
                    throw ValidationException::withMessages([
                        'allocations' => ["La referencia es obligatoria para pagos por {$method->label()}."],
                    ]);
                }

                $payment = $lockedRunOrder->payments()->create([
                    'receipt_number' => $this->generateNumber->handle('receipt', 'REC', $settledAt),
                    'status' => DeliveryPaymentStatus::Active,
                    'method' => $paymentMethod,
                    'amount' => $amount,
                    'reference' => $reference,
                    'received_at' => $settledAt,
                    'received_by' => $actor->id,
                ]);
                CustomerAccountEntry::query()->create([
                    'customer_id' => $order->customer_id,
                    'type' => CustomerAccountEntryType::DeliveryPayment,
                    'status' => CustomerAccountEntryStatus::Active,
                    'receipt_number' => $payment->receipt_number,
                    'order_id' => $order->id,
                    'delivery_run_order_id' => $lockedRunOrder->id,
                    'delivery_payment_id' => $payment->id,
                    'method' => $paymentMethod,
                    'debit_amount' => '0.0000',
                    'credit_amount' => $amount,
                    'currency' => $order->currency,
                    'reference' => $reference,
                    'occurred_at' => $settledAt,
                    'created_by' => $actor->id,
                ]);
                $settlement->lines()->create([
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $reference,
                    'delivery_payment_id' => $payment->id,
                ]);
                $collectedTotal = bcadd($collectedTotal, $amount, 4);
            }

            $finalStatus = $lockedRunOrder->outcome_status;

            if (! $finalStatus instanceof DeliveryOrderStatus || ! $finalStatus->isCompleted()) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['El resultado guardado no es válido para cerrar la entrega.'],
                ]);
            }

            $lockedRunOrder->forceFill([
                'status' => $finalStatus,
                'collected_total' => $collectedTotal,
                'balance_due' => $creditTotal,
                'credit_reason' => $creditTotal === '0.0000' ? null : $data['credit_reason'],
                'completed_at' => $settledAt,
                'completed_by' => $actor->id,
            ])->save();

            $orderStatus = match ($finalStatus) {
                DeliveryOrderStatus::Delivered => OrderStatus::Delivered,
                DeliveryOrderStatus::PartiallyDelivered => OrderStatus::PartiallyDelivered,
                DeliveryOrderStatus::NotDelivered => OrderStatus::NotDelivered,
                default => throw new \LogicException('Estado de entrega inesperado.'),
            };

            if ($order->status !== $orderStatus) {
                $previousStatus = $order->status;
                $order->forceFill(['status' => $orderStatus])->save();
                $order->statusHistory()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $orderStatus,
                    'changed_by' => $actor->id,
                    'reason' => $finalStatus->label()." en la jornada {$run->run_number}.",
                ]);
            }

            $allCompleted = $run->runOrders()
                ->whereNotIn('status', collect(DeliveryOrderStatus::cases())
                    ->filter(fn (DeliveryOrderStatus $status): bool => $status->isCompleted())
                    ->map->value)
                ->doesntExist();

            if ($allCompleted && $run->status !== DeliveryRunStatus::AwaitingSettlement) {
                $run->forceFill(['status' => DeliveryRunStatus::AwaitingSettlement])->save();
                $run->statusHistory()->create([
                    'from_status' => DeliveryRunStatus::InTransit,
                    'to_status' => DeliveryRunStatus::AwaitingSettlement,
                    'changed_by' => $actor->id,
                    'reason' => 'Todas las paradas tienen resultado y cierre financiero.',
                ]);
            }

            $this->refreshFinancials->handle($run);

            return $settlement->load(['lines.deliveryPayment', 'lines.receivable', 'settledBy']);
        }, 3);

        $refreshedRun = $deliveryRun->fresh();

        if ($refreshedRun?->status === DeliveryRunStatus::AwaitingSettlement) {
            $this->notifications->deliveryRunReadyForSettlement($refreshedRun);
        }

        return $settlement;
    }
}
