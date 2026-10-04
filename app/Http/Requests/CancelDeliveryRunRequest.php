<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;

class CancelDeliveryRunRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun
            && ($this->user()?->can('cancel', $deliveryRun) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => $this->nullableString('reason')]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
