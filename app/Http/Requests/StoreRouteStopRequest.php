<?php

namespace App\Http\Requests;

use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Weekday;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreRouteStopRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $notes = $this->string('notes')->trim()->toString();
        $this->merge(['notes' => $notes !== '' ? $notes : null]);
    }

    public function rules(): array
    {
        /** @var SalesRoute $salesRoute */
        $salesRoute = $this->route('salesRoute');
        $stop = $this->route('stop');

        return [
            'customer_id' => [
                'required',
                'ulid',
                Rule::exists('customers', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->whereNull('deleted_at')
                        ->where(function (Builder $customers) use ($stop): void {
                            $customers->where('is_active', true);

                            if ($stop instanceof RouteStop) {
                                $customers->orWhere('id', $stop->customer_id);
                            }
                        }),
                ),
                Rule::unique('route_stops', 'customer_id')
                    ->where('sales_route_id', $salesRoute->id)
                    ->where('visit_day', $this->integer('visit_day'))
                    ->ignore($stop),
            ],
            'visit_day' => ['required', 'integer', Rule::enum(Weekday::class)],
            'visit_order' => ['required', 'integer', 'min:1', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.unique' => 'Este cliente ya está programado en la ruta para ese día.',
        ];
    }

    protected function ability(): string
    {
        return 'manage-routes';
    }
}
