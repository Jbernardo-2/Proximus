<?php

namespace App\Http\Requests;

class ReverseCustomerPaymentRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => $this->string('reason')->trim()->toString()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function ability(): string
    {
        return 'reverse-customer-payments';
    }
}
