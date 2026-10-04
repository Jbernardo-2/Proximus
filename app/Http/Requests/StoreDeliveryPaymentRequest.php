<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreDeliveryPaymentRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        $deliveryRun = $this->route('deliveryRun');
        $runOrder = $this->route('runOrder');

        return $deliveryRun instanceof DeliveryRun
            && $runOrder instanceof DeliveryRunOrder
            && $runOrder->delivery_run_id === $deliveryRun->id
            && ($this->user()?->can('execute', $deliveryRun) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_reference' => $this->nullableString('client_reference'),
            'reference' => $this->nullableString('reference'),
            'notes' => $this->nullableString('notes'),
            'received_at' => $this->nullableString('received_at'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
