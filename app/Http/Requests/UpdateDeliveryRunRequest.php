<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use Illuminate\Validation\Rule;

class UpdateDeliveryRunRequest extends StoreDeliveryRunRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun && ($this->user()?->can('update', $deliveryRun) ?? false);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['client_reference'] = [
            'nullable',
            'string',
            'max:100',
            Rule::unique('delivery_runs', 'client_reference')->ignore($this->route('deliveryRun')),
        ];

        return $rules;
    }
}
