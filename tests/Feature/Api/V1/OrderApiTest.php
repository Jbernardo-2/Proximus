<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderPriceSource;
use App\OrderStatus;
use App\PaymentTerm;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_order_endpoints_enforce_authentication_ability_and_role(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        $this->getJson('/api/v1/orders')->assertForbidden();

        Sanctum::actingAs(User::factory()->repartidor()->create(), ['orders:view']);
        $this->getJson('/api/v1/orders')->assertForbidden();
    }

    public function test_preventista_creates_quotes_adds_and_confirms_order_through_api(): void
    {
        [$user, $customer, $stop] = $this->routeContext();
        [, $unit, $box] = $this->productWithPresentations();
        Sanctum::actingAs($user, ['orders:view', 'orders:manage']);

        $response = $this->postJson('/api/v1/orders', [
            'client_reference' => 'mobile-order-001',
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $user->id,
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => null,
            'payment_term' => PaymentTerm::Cash->value,
            'notes' => 'Pedido desde tablet',
            'status' => OrderStatus::Confirmed->value,
            'total' => '1.0000',
        ]);

        $order = Order::query()->sole();
        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.client_reference', 'mobile-order-001')
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.items_count', 0);

        $this->getJson("/api/v1/orders/{$order->id}/item-quote?product_presentation_id={$unit->id}&quantity=30")
            ->assertOk()
            ->assertJsonPath('data.selected.line_total', '300.0000')
            ->assertJsonPath('data.conversion_suggestion.components.0.presentation_id', $box->id)
            ->assertJsonPath('data.conversion_suggestion.components.0.count', '1')
            ->assertJsonPath('data.conversion_suggestion.components.1.count', '6')
            ->assertJsonPath('data.conversion_suggestion.notice', 'Esta es una sugerencia. Las cantidades solo cambian cuando el usuario las confirma.');
        $this->assertDatabaseCount('order_items', 0);

        $this->postJson("/api/v1/orders/{$order->id}/items", [
            'product_presentation_id' => $unit->id,
            'quantity' => '30',
            'notes' => null,
            'price_overridden_by' => 999,
            'line_total' => '0.0001',
        ])->assertCreated()
            ->assertJsonPath('data.quantity', '30.000000')
            ->assertJsonPath('data.unit_price', '10.0000')
            ->assertJsonPath('data.line_total', '300.0000')
            ->assertJsonPath('data.price_source', OrderPriceSource::Presentation->value);

        $this->postJson("/api/v1/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.total', '300.0000');
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->putJson("/api/v1/orders/{$order->id}", [
            'payment_term' => PaymentTerm::Credit->value,
            'requested_delivery_date' => null,
            'notes' => null,
        ])->assertForbidden();
    }

    public function test_preventista_and_bodeguero_lists_are_scoped_without_leaking_other_drafts(): void
    {
        $user = User::factory()->preventista()->create();
        $ownOrder = Order::factory()->create(['salesperson_id' => $user->id, 'customer_name' => 'Cliente propio']);
        $otherDraft = Order::factory()->create(['customer_name' => 'Cliente ajeno']);
        $confirmed = Order::factory()->confirmed()->create(['customer_name' => 'Pedido para bodega']);
        Sanctum::actingAs($user, ['orders:view']);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id);
        $this->getJson("/api/v1/orders/{$otherDraft->id}")->assertForbidden();

        $bodeguero = User::factory()->create();
        Sanctum::actingAs($bodeguero, ['orders:view']);
        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $confirmed->id);
        $this->getJson("/api/v1/orders/{$otherDraft->id}")->assertForbidden();
        $this->getJson("/api/v1/orders/{$confirmed->id}")->assertOk();
    }

    public function test_api_price_override_requires_role_and_explicit_token_ability(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['created_by' => $admin->id]);
        [, $unit] = $this->productWithPresentations();
        $payload = [
            'product_presentation_id' => $unit->id,
            'quantity' => '2',
            'unit_price' => '5.0000',
            'override_reason' => 'Precio especial aprobado',
            'notes' => null,
        ];
        Sanctum::actingAs($admin, ['orders:manage']);

        $this->postJson("/api/v1/orders/{$order->id}/items", $payload)->assertForbidden();
        $this->assertDatabaseCount('order_items', 0);

        Sanctum::actingAs($admin, ['orders:manage', 'orders:override']);
        $this->postJson("/api/v1/orders/{$order->id}/items", $payload)
            ->assertCreated()
            ->assertJsonPath('data.price_source', OrderPriceSource::Override->value)
            ->assertJsonPath('data.standard_unit_price', '10.0000')
            ->assertJsonPath('data.unit_price', '5.0000');
        $this->assertSame($admin->id, OrderItem::query()->sole()->price_overridden_by);
    }

    public function test_supervisor_can_create_out_of_route_and_duplicate_sync_reference_returns_422(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $salesperson = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        Sanctum::actingAs($supervisor, ['orders:manage']);
        $payload = [
            'client_reference' => 'offline-unique-001',
            'customer_id' => $customer->id,
            'route_stop_id' => null,
            'salesperson_id' => $salesperson->id,
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => null,
            'payment_term' => PaymentTerm::Credit->value,
            'notes' => null,
        ];

        $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.route', null)
            ->assertJsonPath('data.salesperson.id', $salesperson->id);
        $this->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_reference')
            ->assertJsonPath('errors.client_reference.0', 'Esta referencia de sincronización ya fue utilizada.');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_lifecycle_actions_require_token_ability_and_reason(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $order = Order::factory()->confirmed()->create(['created_by' => $supervisor->id]);
        Sanctum::actingAs($supervisor, ['orders:view']);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Cancelación aprobada'])
            ->assertForbidden();

        Sanctum::actingAs($supervisor, ['orders:lifecycle']);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Cancelación aprobada'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.cancellation_reason', 'Cancelación aprobada');
    }

    /** @return array{User, Customer, RouteStop} */
    private function routeContext(): array
    {
        $user = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        $route = SalesRoute::factory()->for($user, 'salesperson')->create();
        $stop = RouteStop::factory()->for($route)->for($customer)->create();

        return [$user, $customer, $stop];
    }

    /** @return array{Product, ProductPresentation, ProductPresentation} */
    private function productWithPresentations(): array
    {
        $baseUnit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'symbol' => 'ud']);
        $product = Product::factory()->for($baseUnit, 'baseUnit')->create([
            'name' => 'Arroz Premium',
            'sku' => fake()->unique()->bothify('ARR-###'),
        ]);
        $unit = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '10.0000',
        ]);
        $box = ProductPresentation::factory()->for($product)->create([
            'name' => 'Caja 24',
            'conversion_factor' => '24.000000',
            'sale_price' => '220.0000',
        ]);

        return [$product, $unit, $box];
    }
}
