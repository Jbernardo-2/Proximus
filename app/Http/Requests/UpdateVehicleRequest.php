<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle && ($this->user()?->can('update', $vehicle) ?? false);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('vehicles', 'code')->ignore($vehicle)],
            'license_plate' => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'license_plate')->ignore($vehicle)],
            'description' => ['required', 'string', 'max:180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $isActive = $this->isJson()
            ? $this->input('is_active')
            : $this->boolean('is_active');

        parent::prepareForValidation();
        $this->merge(['is_active' => $isActive]);
    }
}
