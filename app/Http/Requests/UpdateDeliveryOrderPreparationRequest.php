<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateDeliveryOrderPreparationRequest extends DeliveryRequest
{
    public function authorize(): bool|Response
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $runOrder->delivery_run_id === $deliveryRun->id
            ? Gate::forUser($this->user())->inspect('prepare', $deliveryRun)
            : false;
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
