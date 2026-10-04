<?php

namespace App\Http\Requests;

use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;

class VoidDeliveryPaymentRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');
        $payment = $this->route('payment');
        $user = $this->user();

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $payment instanceof DeliveryPayment
            && $runOrder->delivery_run_id === $deliveryRun->id
            && $payment->delivery_run_order_id === $runOrder->id
            && $user !== null
            && $user->canSettleDeliveries();
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
