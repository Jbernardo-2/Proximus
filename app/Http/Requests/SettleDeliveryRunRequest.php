<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;

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

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'credit_reasons.*' => 'justificación del saldo a crédito',
        ];
    }
}
