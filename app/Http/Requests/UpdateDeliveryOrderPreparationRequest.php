<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;

class UpdateDeliveryOrderPreparationRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $runOrder->delivery_run_id === $deliveryRun->id
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
