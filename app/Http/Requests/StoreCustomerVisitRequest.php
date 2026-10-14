<?php

namespace App\Http\Requests;

use App\CustomerVisitReason;
use Illuminate\Validation\Rule;

class StoreCustomerVisitRequest extends OperationsRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(CustomerVisitReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'reason' => 'motivo de la visita sin pedido',
        ];
    }

    protected function ability(): string
    {
        return 'manage-orders';
    }
}
