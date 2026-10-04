<?php

namespace App\Actions;

use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDeliveryPaymentAction
{
    public function __construct(
        private GenerateDeliveryNumberAction $generateNumber,
        private RefreshDeliveryFinancialsAction $refreshFinancials,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        array $data,
        User $actor,
    ): DeliveryPayment {
        $clientReference = $data['client_reference'] ?? null;

        try {
            return DB::transaction(function () use ($deliveryRun, $runOrder, $data, $actor, $clientReference): DeliveryPayment {

                if ($clientReference !== null) {
                    $existing = DeliveryPayment::query()
                        ->where('client_reference', $clientReference)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        $this->ensureSamePayload($existing, $runOrder, $data);

                        return $existing;
                    }
                }

                $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

                if (! in_array($run->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true)) {
                    throw ValidationException::withMessages([
                        'delivery_run' => ['La jornada no acepta cobros en su estado actual.'],
                    ]);
                }

                $lockedRunOrder = $run->runOrders()->lockForUpdate()->findOrFail($runOrder->id);

                if (! $lockedRunOrder->status->isCompleted()) {
                    throw ValidationException::withMessages([
                        'delivery_run_order' => ['Registra primero el resultado de la entrega.'],
                    ]);
                }

                $method = PaymentMethod::from($data['method']);

                if ($method->requiresReference() && empty($data['reference'])) {
                    throw ValidationException::withMessages([
                        'reference' => ["La referencia es obligatoria para pagos por {$method->label()}."],
                    ]);
                }

                $amount = (string) $data['amount'];
                $alreadyCollected = (string) $lockedRunOrder->payments()
                    ->where('status', DeliveryPaymentStatus::Active->value)
                    ->sum('amount');
                $remaining = bcsub((string) $lockedRunOrder->delivered_total, $alreadyCollected, 4);

                if (bccomp($amount, '0', 4) <= 0 || bccomp($amount, $remaining, 4) > 0) {
                    throw ValidationException::withMessages([
                        'amount' => ['El cobro debe ser mayor que cero y no puede superar el saldo de la entrega.'],
                    ]);
                }

                $receivedAt = isset($data['received_at'])
                    ? CarbonImmutable::parse((string) $data['received_at'])
                    : now();
                $payment = $lockedRunOrder->payments()->create([
                    'receipt_number' => $this->generateNumber->handle('receipt', 'REC', $receivedAt),
                    'client_reference' => $clientReference,
                    'status' => DeliveryPaymentStatus::Active,
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_at' => $receivedAt,
                    'received_by' => $actor->id,
                ]);
                $this->refreshFinancials->handle($run);

                return $payment;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($clientReference === null) {
                throw $exception;
            }

            $existing = DeliveryPayment::query()
                ->where('client_reference', $clientReference)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->ensureSamePayload($existing, $runOrder, $data);

            return $existing;
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureSamePayload(
        DeliveryPayment $payment,
        DeliveryRunOrder $runOrder,
        array $data,
    ): void {
        $samePayload = $payment->delivery_run_order_id === $runOrder->id
            && $payment->method->value === $data['method']
            && bccomp((string) $payment->amount, (string) $data['amount'], 4) === 0
            && $payment->reference === ($data['reference'] ?? null);

        if (! $samePayload) {
            throw ValidationException::withMessages([
                'client_reference' => ['La referencia ya fue usada por un cobro diferente.'],
            ]);
        }
    }
}
