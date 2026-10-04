<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreDeliveryRunRequest extends DeliveryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DeliveryRun::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_reference' => $this->nullableString('client_reference'),
            'vehicle_id' => $this->nullableIdentifier('vehicle_id'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_reference' => ['nullable', 'string', 'max:100', Rule::unique('delivery_runs', 'client_reference')],
            'warehouse_id' => ['required', 'ulid', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'driver_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where('role', UserRole::Repartidor->value),
                ),
            ],
            'vehicle_id' => [
                'nullable',
                'ulid',
                Rule::exists('vehicles', 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
