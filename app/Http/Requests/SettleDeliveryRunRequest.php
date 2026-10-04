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
        $this->merge(['settlement_notes' => $this->nullableString('settlement_notes')]);
    }

    public function rules(): array
    {
        return [
            'cash_declared' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'settlement_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
