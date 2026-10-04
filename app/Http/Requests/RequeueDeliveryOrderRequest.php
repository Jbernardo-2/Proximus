<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;

class RequeueDeliveryOrderRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $runOrder->delivery_run_id === $deliveryRun->id
            && ($this->user()?->canManageOrderLifecycle() ?? false);
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
