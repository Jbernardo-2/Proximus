<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\PaymentMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerPaymentRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $paymentLines = $this->input('payment_lines');

        if (! is_array($paymentLines)) {
            $paymentLines = [[
                'method' => $this->input('method'),
                'amount' => $this->input('amount'),
                'reference' => $this->input('reference'),
            ]];
        }

        $paymentLines = collect($paymentLines)
            ->map(function (mixed $line): mixed {
                if (! is_array($line)) {
                    return $line;
                }

                return [
                    'method' => trim((string) ($line['method'] ?? '')),
                    'amount' => trim((string) ($line['amount'] ?? '')),
                    'reference' => filled($line['reference'] ?? null)
                        ? trim((string) $line['reference'])
                        : null,
                ];
            })
            ->all();
        $allocations = collect($this->input('allocations', []))
            ->filter(function (mixed $allocation): bool {
                if (! is_array($allocation)) {
                    return true;
                }

                $amount = trim((string) ($allocation['amount'] ?? ''));

                return ! in_array($amount, ['', '0', '0.0', '0.00', '0.000', '0.0000'], true);
            })
            ->values()
            ->all();

        $this->merge([
            'amount' => trim((string) $this->input('amount', '')),
            'notes' => $this->nullableString('notes'),
            'receipt_number' => $this->nullableString('receipt_number'),
            'payment_lines' => $paymentLines,
            'allocations' => $allocations,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt_number' => ['nullable', 'string', 'max:40'],
            'payment_lines' => ['required', 'array', 'min:1', 'max:10'],
            'payment_lines.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_lines.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'payment_lines.*.reference' => ['nullable', 'string', 'max:120'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.receivable_id' => ['required', 'distinct', 'ulid', 'exists:customer_receivables,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'amount',
                    'payment_lines',
                    'payment_lines.*.method',
                    'payment_lines.*.amount',
                    'allocations',
                    'allocations.*.receivable_id',
                    'allocations.*.amount',
                ])) {
                    return;
                }

                $customer = $this->route('customer');
                $total = '0.0000';
                $paymentTotal = '0.0000';

                foreach ($this->input('payment_lines', []) as $index => $line) {
                    $method = PaymentMethod::tryFrom((string) $line['method']);
                    $paymentTotal = bcadd($paymentTotal, (string) $line['amount'], 4);

                    if ($method?->requiresReference() && blank($line['reference'] ?? null)) {
                        $validator->errors()->add(
                            "payment_lines.{$index}.reference",
                            "La referencia es obligatoria para pagos por {$method->label()}.",
                        );
                    }
                }

                if (bccomp($paymentTotal, (string) $this->input('amount'), 4) !== 0) {
                    $validator->errors()->add('payment_lines', 'Los métodos de pago deben sumar exactamente el total recibido.');
                }

                foreach ($this->input('allocations', []) as $index => $allocation) {
                    $total = bcadd($total, (string) $allocation['amount'], 4);
                    $receivable = CustomerReceivable::query()->find($allocation['receivable_id']);

                    if (! $customer instanceof Customer || $receivable?->customer_id !== $customer->id) {
                        $validator->errors()->add(
                            "allocations.{$index}.receivable_id",
                            'La deuda seleccionada no pertenece a este cliente.',
                        );
                    }
                }

                if (bccomp($total, (string) $this->input('amount'), 4) !== 0) {
                    $validator->errors()->add('allocations', 'La aplicación a deudas debe sumar exactamente el abono.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'amount' => 'monto del abono',
            'payment_lines' => 'métodos de pago',
            'payment_lines.*.method' => 'método de pago',
            'payment_lines.*.amount' => 'monto del método',
            'payment_lines.*.reference' => 'referencia del método',
            'allocations' => 'aplicación del abono',
            'allocations.*.receivable_id' => 'deuda',
            'allocations.*.amount' => 'monto aplicado',
        ];
    }

    protected function ability(): string
    {
        return 'record-customer-payments';
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }
}
