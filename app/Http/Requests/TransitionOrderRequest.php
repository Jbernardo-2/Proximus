<?php

namespace App\Http\Requests;

use App\Models\Order;

class TransitionOrderRequest extends OrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        if (! $order instanceof Order) {
            return false;
        }

        $ability = $this->routeIs('orders.cancel', 'api.v1.orders.cancel') ? 'cancel' : 'reopen';

        return $this->user()?->can($ability, $order) ?? false;
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
