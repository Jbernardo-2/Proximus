<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Weekday;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerRouteApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_customer_and_returns_201(): void
    {
        Sanctum::actingAs(User::factory()->preventista()->create(), ['customers:manage']);

        $response = $this->postJson('/api/v1/customers', [
            'code' => 'cli-api-01',
            'business_name' => 'Minimarket Central',
            'business_type' => 'Minimarket',
            'contact_name' => 'Ana Reyes',
            'phone' => '2222-0101',
            'whatsapp' => null,
            'email' => null,
            'address' => 'Colonia Centro, calle principal',
            'reference' => null,
            'latitude' => null,
            'longitude' => null,
            'notes' => null,
            'is_active' => true,
            'deleted_at' => now()->toISOString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'CLI-API-01')
            ->assertJsonPath('data.business_name', 'Minimarket Central')
            ->assertJsonPath('data.route_stops_count', 0);
        $this->assertDatabaseHas('customers', [
            'code' => 'CLI-API-01',
            'business_name' => 'Minimarket Central',
            'deleted_at' => null,
        ]);
    }

    public function test_customer_endpoint_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_customer_endpoint_returns_403_without_required_ability(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);

        $this->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_customer_endpoint_returns_403_for_role_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['customers:manage']);

        $this->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_api_allows_customer_in_multiple_routes_and_returns_schedule(): void
    {
        Sanctum::actingAs(User::factory()->preventista()->create(), ['routes:manage']);
        $customer = Customer::factory()->create();
        $firstRoute = SalesRoute::factory()->create();
        $secondRoute = SalesRoute::factory()->create();

        $this->postJson("/api/v1/routes/{$firstRoute->id}/stops", [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Wednesday->value,
            'visit_order' => 1,
            'notes' => 'Primera visita',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.visit_day', Weekday::Wednesday->value)
            ->assertJsonPath('data.visit_day_label', 'Miércoles')
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->postJson("/api/v1/routes/{$secondRoute->id}/stops", [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Wednesday->value,
            'visit_order' => 5,
            'notes' => null,
            'is_active' => true,
        ])->assertCreated();

        $this->postJson("/api/v1/routes/{$firstRoute->id}/stops", [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Saturday->value,
            'visit_order' => 2,
            'notes' => null,
            'is_active' => true,
        ])->assertCreated();

        $this->assertDatabaseCount('route_stops', 3);

        $this->getJson("/api/v1/routes/{$firstRoute->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonPath('data.stops.0.visit_day_label', 'Miércoles')
            ->assertJsonPath('data.stops.1.visit_day_label', 'Sábado');
    }

    public function test_duplicate_customer_day_in_same_route_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->preventista()->create(), ['routes:manage']);
        $salesRoute = SalesRoute::factory()->create();
        $stop = RouteStop::factory()->for($salesRoute)->create([
            'visit_day' => Weekday::Monday,
        ]);

        $this->postJson("/api/v1/routes/{$salesRoute->id}/stops", [
            'customer_id' => $stop->customer_id,
            'visit_day' => Weekday::Monday->value,
            'visit_order' => 9,
            'notes' => null,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id')
            ->assertJsonPath('errors.customer_id.0', 'Este cliente ya está programado en la ruta para ese día.');

        $this->assertDatabaseCount('route_stops', 1);
    }

    public function test_route_creation_returns_422_for_incorrect_personnel_roles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['routes:manage']);
        $bodeguero = User::factory()->create();
        $preventista = User::factory()->preventista()->create();

        $this->postJson('/api/v1/routes', [
            'code' => 'RUTA-API',
            'name' => 'Ruta API',
            'description' => null,
            'salesperson_id' => $bodeguero->id,
            'driver_id' => $preventista->id,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['salesperson_id', 'driver_id'])
            ->assertJsonPath('errors.salesperson_id.0', 'El valor seleccionado para preventista no es válido.')
            ->assertJsonPath('errors.driver_id.0', 'El valor seleccionado para repartidor no es válido.');

        $this->assertDatabaseCount('sales_routes', 0);
    }

    public function test_scoped_binding_returns_404_for_stop_from_another_route(): void
    {
        Sanctum::actingAs(User::factory()->preventista()->create(), ['routes:manage']);
        $firstRoute = SalesRoute::factory()->create();
        $secondRoute = SalesRoute::factory()->create();
        $stop = RouteStop::factory()->for($secondRoute)->create([
            'visit_day' => Weekday::Tuesday,
            'visit_order' => 3,
        ]);

        $this->putJson("/api/v1/routes/{$firstRoute->id}/stops/{$stop->id}", [
            'customer_id' => $stop->customer_id,
            'visit_day' => Weekday::Friday->value,
            'visit_order' => 8,
            'notes' => null,
            'is_active' => true,
        ])->assertNotFound();

        $this->assertSame(Weekday::Tuesday, $stop->refresh()->visit_day);
        $this->assertSame(3, $stop->visit_order);
    }
}
