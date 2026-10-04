<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'license_plate' => $this->nullableString('license_plate'),
            'is_active' => $this->isJson()
                ? $this->input('is_active', true)
                : $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('vehicles', 'code')],
            'license_plate' => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'license_plate')],
            'description' => ['required', 'string', 'max:180'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
