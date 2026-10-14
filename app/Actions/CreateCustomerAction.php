<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a customer, optionally scheduling it on a route. A preventista's
 * customer always lands on one of their routes, otherwise they could not see it.
 */
class CreateCustomerAction
{
    /**
     * @param  array<string, mixed>  $data  Customer fields, plus optional `id` (ULID generated on the phone) and `route_stop`.
     */
    public function handle(array $data, User $actor): Customer
    {
        $stopData = $data['route_stop'] ?? null;

        if ($actor->role === UserRole::Preventista) {
            $stopData = $this->preventistaStop($actor, is_array($stopData) ? $stopData : []);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $stopData): Customer {
                    $customer = new Customer(Arr::except($data, ['id', 'route_stop']));
                    $customer->code = blank($data['code'] ?? null) ? $this->nextCode() : $data['code'];

                    if (filled($data['id'] ?? null)) {
                        $customer->id = $data['id'];
                    }

                    $customer->save();

                    if (is_array($stopData)) {
                        $stop = new RouteStop;
                        $stop->id = $stopData['id'] ?? $stop->newUniqueId();
                        $stop->fill([
                            'sales_route_id' => $stopData['sales_route_id'],
                            'customer_id' => $customer->id,
                            'visit_day' => $stopData['visit_day'],
                            'visit_order' => $stopData['visit_order'] ?? ((int) RouteStop::query()
                                ->where('sales_route_id', $stopData['sales_route_id'])
                                ->where('visit_day', $stopData['visit_day'])
                                ->max('visit_order')) + 1,
                            'is_active' => true,
                        ])->save();
                    }

                    return $customer;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (filled($data['code'] ?? null) || $attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stopData
     * @return array<string, mixed>
     */
    private function preventistaStop(User $actor, array $stopData): array
    {
        $routes = SalesRoute::query()->active()->where('salesperson_id', $actor->id)->pluck('id');
        $routeId = $stopData['sales_route_id'] ?? ($routes->count() === 1 ? $routes->first() : null);

        if ($routeId === null || ! $routes->contains($routeId)) {
            throw ValidationException::withMessages([
                'route_stop.sales_route_id' => [$routes->isEmpty()
                    ? 'No tienes una ruta activa asignada para agregar clientes.'
                    : 'Selecciona en cuál de tus rutas queda el cliente.'],
            ]);
        }

        return [
            'sales_route_id' => $routeId,
            'visit_day' => $stopData['visit_day'] ?? now()->dayOfWeekIso,
            'visit_order' => $stopData['visit_order'] ?? null,
            'id' => $stopData['id'] ?? null,
        ];
    }

    private function nextCode(): string
    {
        $last = Customer::withTrashed()
            ->where('code', 'like', 'CLI-%')
            ->orderByRaw('length(code) desc')
            ->orderByDesc('code')
            ->value('code');

        return 'CLI-'.(((int) substr((string) $last, 4)) + 1);
    }
}
