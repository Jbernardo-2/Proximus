<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Weekday;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SalesRouteManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supervisor_can_create_route_with_valid_personnel(): void
    {
        $user = User::factory()->supervisor()->create();
        $salesperson = User::factory()->preventista()->create();
        $driver = User::factory()->repartidor()->create();

        $response = $this->actingAs($user)->post(route('routes.store'), [
            'code' => 'ruta-norte',
            'name' => 'Ruta Norte',
            'description' => 'Barrios del sector norte',
            'salesperson_id' => $salesperson->id,
            'driver_id' => $driver->id,
            'is_active' => '1',
        ]);

        $salesRoute = SalesRoute::query()->sole();
        $response
            ->assertRedirect(route('routes.show', $salesRoute))
            ->assertSessionHas('success', 'Ruta creada correctamente.');
        $this->assertDatabaseHas('sales_routes', [
            'id' => $salesRoute->id,
            'code' => 'RUTA-NORTE',
            'salesperson_id' => $salesperson->id,
            'driver_id' => $driver->id,
        ]);
    }

    public function test_route_rejects_users_with_incorrect_operational_roles(): void
    {
        $user = User::factory()->admin()->create();
        $bodeguero = User::factory()->create();
        $preventista = User::factory()->preventista()->create();

        $this->actingAs($user)
            ->post(route('routes.store'), [
                'code' => 'RUTA-ERROR',
                'name' => 'Ruta inválida',
                'salesperson_id' => $bodeguero->id,
                'driver_id' => $preventista->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors([
                'salesperson_id' => 'El valor seleccionado para preventista no es válido.',
                'driver_id' => 'El valor seleccionado para repartidor no es válido.',
            ]);

        $this->assertDatabaseCount('sales_routes', 0);
    }

    public function test_customer_can_have_visits_in_multiple_routes_and_days(): void
    {
        $user = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        $northRoute = SalesRoute::factory()->create();
        $southRoute = SalesRoute::factory()->create();

        $this->actingAs($user)->post(route('routes.stops.store', $northRoute), [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Monday->value,
            'visit_order' => 1,
            'notes' => null,
            'is_active' => '1',
        ])->assertRedirect(route('routes.show', $northRoute));

        $this->actingAs($user)->post(route('routes.stops.store', $southRoute), [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Monday->value,
            'visit_order' => 3,
            'notes' => null,
            'is_active' => '1',
        ])->assertRedirect(route('routes.show', $southRoute));

        $this->actingAs($user)->post(route('routes.stops.store', $northRoute), [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Thursday->value,
            'visit_order' => 2,
            'notes' => null,
            'is_active' => '1',
        ])->assertRedirect(route('routes.show', $northRoute));

        $this->assertDatabaseCount('route_stops', 3);
        $this->assertDatabaseHas('route_stops', [
            'sales_route_id' => $southRoute->id,
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Monday->value,
        ]);

        $this->actingAs($user)->post(route('routes.stops.store', $northRoute), [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Monday->value,
            'visit_order' => 9,
            'notes' => null,
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'customer_id' => 'Este cliente ya está programado en la ruta para ese día.',
        ]);

        $this->assertDatabaseCount('route_stops', 3);
    }

    public function test_scoped_binding_returns_404_for_stop_from_another_route(): void
    {
        $user = User::factory()->supervisor()->create();
        $firstRoute = SalesRoute::factory()->create();
        $secondRoute = SalesRoute::factory()->create();
        $stop = RouteStop::factory()->for($secondRoute)->create([
            'visit_day' => Weekday::Tuesday,
            'visit_order' => 4,
        ]);

        $this->actingAs($user)
            ->put(route('routes.stops.update', [$firstRoute, $stop]), [
                'customer_id' => $stop->customer_id,
                'visit_day' => Weekday::Friday->value,
                'visit_order' => 8,
                'notes' => 'No debe cambiar',
                'is_active' => '1',
            ])
            ->assertNotFound();

        $this->assertSame(Weekday::Tuesday, $stop->refresh()->visit_day);
        $this->assertSame(4, $stop->visit_order);
    }

    public function test_existing_visit_can_be_reordered_after_customer_is_deactivated(): void
    {
        $user = User::factory()->supervisor()->create();
        $salesRoute = SalesRoute::factory()->create();
        $customer = Customer::factory()->inactive()->create();
        $stop = RouteStop::factory()->for($salesRoute)->for($customer)->create([
            'visit_day' => Weekday::Wednesday,
            'visit_order' => 2,
        ]);

        $this->actingAs($user)
            ->put(route('routes.stops.update', [$salesRoute, $stop]), [
                'customer_id' => $customer->id,
                'visit_day' => Weekday::Wednesday->value,
                'visit_order' => 7,
                'notes' => 'Atender solo si llama',
                'is_active' => '0',
            ])
            ->assertRedirect(route('routes.show', $salesRoute));

        $this->assertSame(7, $stop->refresh()->visit_order);
        $this->assertFalse($stop->is_active);
    }

    public function test_route_page_renders_visits_and_keeps_configuration_panel_in_document_flow(): void
    {
        $user = User::factory()->supervisor()->create();
        $salesRoute = SalesRoute::factory()->create();
        $customer = Customer::factory()->create([
            'business_name' => "Negocio <script>alert('xss')</script>",
        ]);
        RouteStop::factory()->for($salesRoute)->for($customer)->create();

        $this->actingAs($user)
            ->get(route('routes.show', $salesRoute))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertSee('Configurar')
            ->assertSee('Guardar visita')
            ->assertDontSee('absolute right-0 z-20', false);
    }

    public function test_bodeguero_is_forbidden_from_route_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('routes.index'))->assertForbidden();
    }

    public function test_preventista_only_sees_assigned_routes_and_cannot_configure_them(): void
    {
        $user = User::factory()->preventista()->create();
        $assignedRoute = SalesRoute::factory()->for($user, 'salesperson')->create(['name' => 'Ruta asignada']);
        $otherRoute = SalesRoute::factory()->create(['name' => 'Ruta privada']);
        $customer = Customer::factory()->create();
        RouteStop::factory()->for($assignedRoute)->for($customer)->create(['visit_day' => Weekday::Monday]);
        RouteStop::factory()->for($otherRoute)->for($customer)->create(['visit_day' => Weekday::Tuesday]);

        $this->actingAs($user)
            ->get(route('routes.index'))
            ->assertOk()
            ->assertSee('Ruta asignada')
            ->assertDontSee('Ruta privada')
            ->assertDontSee('Nueva ruta');
        $this->actingAs($user)
            ->get(route('routes.show', $assignedRoute))
            ->assertOk()
            ->assertDontSee('Editar ruta')
            ->assertDontSee('Agregar visita');
        $this->actingAs($user)->get(route('routes.show', $otherRoute))->assertForbidden();
        $this->actingAs($user)->get(route('routes.create'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Ruta asignada')
            ->assertDontSee('Ruta privada');
    }
}
