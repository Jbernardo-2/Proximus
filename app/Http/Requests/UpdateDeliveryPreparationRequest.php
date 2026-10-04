<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;

class UpdateDeliveryPreparationRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun
            && ($this->user()?->can('prepare', $deliveryRun) ?? false);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid', 'distinct', 'exists:delivery_run_items,id'],
            'items.*.prepared_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
        ];
    }
}
