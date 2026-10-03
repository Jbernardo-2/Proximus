<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMeasurementUnitRequest extends CatalogRequest
{
    public function rules(): array
    {
        $unit = $this->route('measurement_unit');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('measurement_units', 'name')->ignore($unit)],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('measurement_units', 'symbol')->ignore($unit)],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
