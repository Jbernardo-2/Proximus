<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\PaymentTerm;
use Illuminate\Validation\Validator;

class SettleDeliveryRunRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun
            && ($this->user()?->can('settle', $deliveryRun) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $creditReasons = $this->input('credit_reasons');

        if (is_array($creditReasons)) {
            $creditReasons = collect($creditReasons)
                ->map(fn (mixed $reason): mixed => is_string($reason) ? trim($reason) : $reason)
                ->all();
        }

        $this->merge([
            'settlement_notes' => $this->nullableString('settlement_notes'),
            'credit_reasons' => $creditReasons,
        ]);
    }

    public function rules(): array
    {
        return [
            'cash_declared' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'settlement_notes' => ['nullable', 'string', 'max:3000'],
            'credit_reasons' => ['nullable', 'array'],
            'credit_reasons.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $deliveryRun = $this->route('deliveryRun');

                if (! $deliveryRun instanceof DeliveryRun) {
                    return;
                }

                $runOrders = $deliveryRun->runOrders()->with('order')->get();

                foreach ($runOrders as $runOrder) {
                    if ($runOrder->order->payment_term !== PaymentTerm::Cash
                        || bccomp((string) $runOrder->balance_due, '0', 4) <= 0
                        || filled($runOrder->credit_reason)) {
                        continue;
                    }

                    $key = "credit_reasons.{$runOrder->id}";

                    if (blank($this->input($key))) {
                        $validator->errors()->add(
                            $key,
                            "Indica por qué se autorizó el saldo pendiente del pedido {$runOrder->order->order_number}.",
                        );
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'credit_reasons.*' => 'justificación del saldo a crédito',
        ];
    }
}
