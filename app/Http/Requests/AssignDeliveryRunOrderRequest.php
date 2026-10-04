<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use Illuminate\Validation\Rule;

class AssignDeliveryRunOrderRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun
            && ($this->user()?->can('assignOrders', $deliveryRun) ?? false);
    }

    public function rules(): array
    {
        $deliveryRun = $this->route('deliveryRun');
        $deliveryRunId = $deliveryRun instanceof DeliveryRun ? $deliveryRun->id : null;

        return [
            'order_id' => ['required', 'ulid', Rule::exists('orders', 'id')],
            'visit_order' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('delivery_run_orders', 'visit_order')
                    ->where('delivery_run_id', $deliveryRunId),
            ],
        ];
    }
}
