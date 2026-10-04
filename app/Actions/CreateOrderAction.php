<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Warehouse;
use App\OrderStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    public function __construct(private GenerateOrderNumberAction $generateOrderNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $routeStop = isset($data['route_stop_id'])
                ? RouteStop::query()->with(['customer', 'salesRoute.salesperson'])->findOrFail($data['route_stop_id'])
                : null;
            $customer = $routeStop?->customer ?? Customer::query()->findOrFail($data['customer_id']);
            $salesRoute = $routeStop?->salesRoute;
            $orderDate = CarbonImmutable::parse($data['order_date'])->startOfDay();

            if ($routeStop !== null
                && (! $routeStop->is_active || ! $customer->is_active || ! $salesRoute?->is_active)) {
                throw ValidationException::withMessages([
                    'route_stop_id' => ['La visita, el cliente o la ruta ya no está activa.'],
                ]);
            }

            if ($routeStop !== null && $routeStop->customer_id !== $data['customer_id']) {
                throw ValidationException::withMessages([
                    'customer_id' => ['El cliente no corresponde a la visita seleccionada.'],
                ]);
            }

            if ($actor->role === UserRole::Preventista && $salesRoute?->salesperson_id !== $actor->id) {
                throw new AuthorizationException('La visita ya no pertenece a una ruta asignada al usuario.');
            }

            $salesperson = $salesRoute?->salesperson
                ?? User::query()->findOrFail($data['salesperson_id']);
            $warehouse = isset($data['warehouse_id'])
                ? Warehouse::query()->findOrFail($data['warehouse_id'])
                : Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->first();

            if ($warehouse === null || ! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Configura una bodega activa antes de crear pedidos.'],
                ]);
            }

            if (! $salesperson->is_active || $salesperson->role !== UserRole::Preventista) {
                throw ValidationException::withMessages([
                    'salesperson_id' => ['El preventista responsable ya no está activo o cambió de rol.'],
                ]);
            }

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber->handle($orderDate),
                'client_reference' => $data['client_reference'] ?? null,
                'customer_id' => $customer->id,
                'sales_route_id' => $salesRoute?->id,
                'route_stop_id' => $routeStop?->id,
                'salesperson_id' => $salesperson->id,
                'created_by' => $actor->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => $orderDate,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'payment_term' => $data['payment_term'],
                'status' => OrderStatus::Draft,
                'currency' => mb_strtoupper((string) config('proximus.currency', 'HNL')),
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
                'customer_code' => $customer->code,
                'customer_name' => $customer->business_name,
                'customer_address' => $customer->address,
                'route_code' => $salesRoute?->code,
                'route_name' => $salesRoute?->name,
                'route_visit_day' => $routeStop?->visit_day->value,
                'route_visit_order' => $routeStop?->visit_order,
                'salesperson_name' => $salesperson->name,
                'notes' => $data['notes'] ?? null,
                'subtotal' => '0.0000',
                'total' => '0.0000',
            ]);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::Draft,
                'changed_by' => $actor->id,
                'reason' => 'Pedido creado.',
            ]);

            return $order;
        });
    }
}
