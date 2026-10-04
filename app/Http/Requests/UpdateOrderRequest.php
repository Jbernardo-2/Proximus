<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\PaymentTerm;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends OrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('update', $order) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requested_delivery_date' => $this->nullableIdentifier('requested_delivery_date'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');

        return [
            'requested_delivery_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$order->order_date->toDateString(),
            ],
            'payment_term' => ['required', Rule::enum(PaymentTerm::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_delivery_date.after_or_equal' => 'La fecha de entrega no puede ser anterior al pedido.',
        ];
    }
}
