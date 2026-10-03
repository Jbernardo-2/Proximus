<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreSalesRouteRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->string('code')->trim()->upper()->toString(),
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->nullableString('description'),
            'salesperson_id' => $this->nullableIdentifier('salesperson_id'),
            'driver_id' => $this->nullableIdentifier('driver_id'),
        ]);
    }

    public function rules(): array
    {
        $salesRoute = $this->route('salesRoute');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('sales_routes', 'code')->ignore($salesRoute)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'salesperson_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query->where('role', UserRole::Preventista->value),
                ),
            ],
            'driver_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query->where('role', UserRole::Repartidor->value),
                ),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function ability(): string
    {
        return 'manage-routes';
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    private function nullableIdentifier(string $key): mixed
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : $value;
    }
}
