<?php

namespace App\Http\Requests;

use App\DeliveryOutcomeReason;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use Illuminate\Validation\Rule;

class CompleteDeliveryStopRequest extends DeliveryRequest
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
            'outcome_reason' => $this->nullableString('outcome_reason'),
            'outcome_notes' => $this->nullableString('outcome_notes'),
            'credit_reason' => $this->nullableString('credit_reason'),
            'receiver_name' => $this->nullableString('receiver_name'),
            'latitude' => $this->nullableIdentifier('latitude'),
            'longitude' => $this->nullableIdentifier('longitude'),
        ]);
    }

    public function rules(): array
    {
        return [
            'outcome_reason' => ['nullable', Rule::enum(DeliveryOutcomeReason::class)],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
            'credit_reason' => ['nullable', 'string', 'max:2000'],
            'receiver_name' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid', 'distinct', 'exists:delivery_run_items,id'],
            'items.*.delivered_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'items.*.returned_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'items.*.damaged_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'items.*.missing_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
        ];
    }
}
