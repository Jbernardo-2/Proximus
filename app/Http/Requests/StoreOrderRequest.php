<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\RouteStop;
use App\PaymentTerm;
use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends OrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_reference' => $this->nullableString('client_reference'),
            'route_stop_id' => $this->nullableIdentifier('route_stop_id'),
            'salesperson_id' => $this->nullableIdentifier('salesperson_id'),
            'order_date' => $this->input('order_date') ?: now()->toDateString(),
            'requested_delivery_date' => $this->nullableIdentifier('requested_delivery_date'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_reference' => ['nullable', 'string', 'max:100', Rule::unique('orders', 'client_reference')],
            'customer_id' => [
                'required',
                'ulid',
                Rule::exists('customers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'route_stop_id' => [
                'nullable',
                'ulid',
                Rule::exists('route_stops', 'id')->where('is_active', true),
            ],
            'salesperson_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('role', UserRole::Preventista->value)
                        ->where('is_active', true),
                ),
            ],
            'order_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'requested_delivery_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'payment_term' => ['required', Rule::enum(PaymentTerm::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['customer_id', 'route_stop_id', 'salesperson_id'])) {
                return;
            }

            $user = $this->user();
            $routeStopId = $this->input('route_stop_id');

            if ($routeStopId === null) {
                if ($user?->role === UserRole::Preventista) {
                    $validator->errors()->add(
                        'route_stop_id',
                        'El preventista debe tomar el pedido desde una visita de una ruta asignada.',
                    );
                }

                if ($this->input('salesperson_id') === null) {
                    $validator->errors()->add('salesperson_id', 'Selecciona el preventista responsable.');
                }

                return;
            }

            $routeStop = RouteStop::query()
                ->with(['customer', 'salesRoute.salesperson'])
                ->find($routeStopId);

            if ($routeStop === null) {
                return;
            }

            if ($routeStop->customer_id !== $this->input('customer_id')) {
                $validator->errors()->add('customer_id', 'El cliente no corresponde a la visita seleccionada.');
            }

            if (! $routeStop->customer?->is_active || ! $routeStop->salesRoute?->is_active) {
                $validator->errors()->add('route_stop_id', 'La visita, el cliente o la ruta ya no está activa.');
            }

            if ($user?->role === UserRole::Preventista && $routeStop->salesRoute?->salesperson_id !== $user->id) {
                $validator->errors()->add('route_stop_id', 'Esta visita no pertenece a una ruta asignada al usuario.');
            }

            $routeSalespersonId = $routeStop->salesRoute?->salesperson_id;

            if ($routeSalespersonId !== null
                && (! $routeStop->salesRoute?->salesperson?->is_active
                    || $routeStop->salesRoute->salesperson->role !== UserRole::Preventista)) {
                $validator->errors()->add(
                    'route_stop_id',
                    'La ruta no tiene un preventista activo y válido. Corrige la asignación antes de tomar el pedido.',
                );
            }

            if ($routeSalespersonId === null && $this->input('salesperson_id') === null) {
                $validator->errors()->add('salesperson_id', 'La ruta no tiene preventista; selecciona uno para el pedido.');
            }

            if ($routeSalespersonId !== null
                && $this->input('salesperson_id') !== null
                && (int) $this->input('salesperson_id') !== $routeSalespersonId) {
                $validator->errors()->add('salesperson_id', 'El preventista debe coincidir con el asignado a la ruta.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'client_reference.unique' => 'Esta referencia de sincronización ya fue utilizada.',
            'requested_delivery_date.after_or_equal' => 'La fecha de entrega no puede ser anterior al pedido.',
        ];
    }
}
