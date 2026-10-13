<?php

namespace App\Http\Requests;

use App\Models\Order;

class ConfirmOrderRequest extends OrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('confirm', $order) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->string('credit_override_reason')->trim()->toString();
        $this->merge(['credit_override_reason' => $reason !== '' ? $reason : null]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credit_override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
