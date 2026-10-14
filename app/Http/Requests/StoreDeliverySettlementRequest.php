<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\SettlementMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeliverySettlementRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $runOrder->delivery_run_id === $deliveryRun->id
            && ($this->user()?->can('execute', $deliveryRun) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $allocations = collect($this->input('allocations', []))
            ->map(function (mixed $allocation): mixed {
                if (! is_array($allocation)) {
                    return $allocation;
                }

                return [
                    'method' => trim((string) ($allocation['method'] ?? '')),
                    'amount' => trim((string) ($allocation['amount'] ?? '')),
                    'reference' => filled($allocation['reference'] ?? null)
                        ? trim((string) $allocation['reference'])
                        : null,
                    'receipt_number' => filled($allocation['receipt_number'] ?? null)
                        ? trim((string) $allocation['receipt_number'])
                        : null,
                ];
            })
            ->all();

        $this->merge([
            'allocations' => $allocations,
            'credit_reason' => $this->nullableString('credit_reason'),
            'credit_override_reason' => $this->nullableString('credit_override_reason'),
            'invoice_number' => $this->nullableString('invoice_number'),
        ]);
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1', 'max:10'],
            'allocations.*.method' => ['required', Rule::enum(SettlementMethod::class)],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'allocations.*.reference' => ['nullable', 'string', 'max:120'],
            'allocations.*.receipt_number' => ['nullable', 'string', 'max:40'],
            'invoice_number' => ['nullable', 'string', 'max:30'],
            'credit_reason' => ['nullable', 'string', 'max:2000'],
            'credit_override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['allocations', 'allocations.*.method', 'allocations.*.amount'])) {
                    return;
                }

                $total = '0.0000';
                $creditLines = 0;

                foreach ($this->input('allocations', []) as $index => $allocation) {
                    $method = SettlementMethod::tryFrom((string) $allocation['method']);
                    $total = bcadd($total, (string) $allocation['amount'], 4);

                    if ($method?->isCredit()) {
                        $creditLines++;
                    }

                    if ($method?->requiresReference() && blank($allocation['reference'] ?? null)) {
                        $validator->errors()->add(
                            "allocations.{$index}.reference",
                            "La referencia es obligatoria para pagos por {$method->label()}.",
                        );
                    }
                }

                if ($creditLines > 1) {
                    $validator->errors()->add('allocations', 'Agrega el crédito en una sola línea.');
                }

                $runOrder = $this->route('runOrder');

                if ($runOrder instanceof DeliveryRunOrder
                    && bccomp($total, (string) $runOrder->delivered_total, 4) !== 0) {
                    $validator->errors()->add(
                        'allocations',
                        'La distribución debe sumar exactamente el total entregado.',
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'allocations' => 'distribución del pago',
            'allocations.*.method' => 'método',
            'allocations.*.amount' => 'monto',
            'allocations.*.reference' => 'referencia',
            'credit_override_reason' => 'motivo de autorización excepcional',
        ];
    }
}
