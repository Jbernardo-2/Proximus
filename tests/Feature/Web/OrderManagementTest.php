<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderPriceSource;
use App\OrderStatus;
use App\PaymentTerm;
use App\Weekday;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preventista_creates_numbered_draft_from_assigned_visit_and_cannot_set_protected_fields(): void
    {
        [$user, $customer, $salesRoute, $stop] = $this->routeContext();
        $otherCustomer = Customer::factory()->create(['business_name' => 'Cliente fuera de la ruta']);
        $otherRoute = SalesRoute::factory()->create(['name' => 'Ruta no asignada']);
        RouteStop::factory()->for($otherRoute)->for($otherCustomer)->create();

        $this->actingAs($user)
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee($customer->business_name)
            ->assertSee($salesRoute->name)
            ->assertDontSee('Cliente fuera de la ruta')
            ->assertDontSee('Ruta no asignada');

        $response = $this->actingAs($user)->post(route('orders.store'), [
            'client_reference' => 'device-001',
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $user->id,
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => now()->addDay()->toDateString(),
            'payment_term' => PaymentTerm::Credit->value,
            'notes' => 'Entregar por la tarde',
            'status' => OrderStatus::Confirmed->value,
            'total' => '0.01',
            'confirmed_by' => $user->id,
        ]);

        $order = Order::query()->sole();
        $response
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success', 'Pedido creado como borrador.');
        $this->assertSame('PED-'.now()->format('Y').'-000001', $order->order_number);
        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame('0.0000', $order->total);
        $this->assertNull($order->confirmed_by);
        $this->assertSame($customer->business_name, $order->customer_name);
        $this->assertSame($salesRoute->name, $order->route_name);
        $this->assertSame(Weekday::Monday->value, $order->route_visit_day);
        $this->assertSame($user->name, $order->salesperson_name);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::Draft->value,
            'changed_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $user->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
        ])->assertRedirect();
        $this->assertSame('PED-'.now()->format('Y').'-000002', Order::query()->latest('order_number')->value('order_number'));
    }

    public function test_preventista_cannot_create_order_from_unassigned_or_mismatched_visit(): void
    {
        $user = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $route = SalesRoute::factory()->for(User::factory()->preventista(), 'salesperson')->create();
        $stop = RouteStop::factory()->for($route)->for($customer)->create();

        $this->actingAs($user)->post(route('orders.store'), [
            'customer_id' => $otherCustomer->id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $user->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
        ])->assertSessionHasErrors(['customer_id', 'route_stop_id', 'salesperson_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quantity_tier_is_snapshotted_and_same_product_accepts_box_and_unit_lines(): void
    {
        $user = User::factory()->preventista()->create();
        $order = Order::factory()->create(['salesperson_id' => $user->id, 'created_by' => $user->id]);
        [$product, $unit, $box] = $this->productWithPresentations();
        PriceTier::factory()->for($unit, 'presentation')->create([
            'min_quantity' => '10',
            'max_quantity' => null,
            'unit_price' => '8.5000',
        ]);

        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '10',
            'unit_price' => null,
            'override_reason' => null,
            'notes' => null,
        ])->assertRedirect(route('orders.show', $order));
        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $box->id,
            'quantity' => '2',
            'unit_price' => null,
            'override_reason' => null,
            'notes' => null,
        ])->assertRedirect(route('orders.show', $order));

        $unitItem = OrderItem::query()->where('product_presentation_id', $unit->id)->sole();
        $boxItem = OrderItem::query()->where('product_presentation_id', $box->id)->sole();
        $this->assertSame($product->id, $unitItem->product_id);
        $this->assertSame('8.5000', $unitItem->unit_price);
        $this->assertSame(OrderPriceSource::PriceTier, $unitItem->price_source);
        $this->assertSame('85.0000', $unitItem->line_total);
        $this->assertSame('48.000000', $boxItem->base_quantity);
        $this->assertSame('565.0000', $order->refresh()->total);
        $this->assertDatabaseCount('order_items', 2);

        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '1',
            'notes' => null,
        ])->assertSessionHasErrors([
            'product_presentation_id' => 'La presentación ya está en el pedido; edita su línea para cambiar la cantidad.',
        ]);
        $this->assertDatabaseCount('order_items', 2);

        $this->actingAs($user)->put(route('orders.items.update', [$order, $unitItem]), [
            'product_presentation_id' => $unit->id,
            'quantity' => '10',
            'unit_price' => '1.0000',
            'override_reason' => 'Descuento no autorizado',
            'notes' => null,
        ])->assertForbidden();
        $this->assertSame('8.5000', $unitItem->refresh()->unit_price);
    }

    public function test_supervisor_price_override_requires_reason_and_records_approver(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $order = Order::factory()->create(['created_by' => $supervisor->id]);
        [, $unit] = $this->productWithPresentations();

        $this->actingAs($supervisor)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '3',
            'unit_price' => '7.0000',
            'override_reason' => null,
            'notes' => null,
        ])->assertSessionHasErrors([
            'override_reason' => 'Debes indicar el motivo del precio autorizado.',
        ]);
        $this->assertDatabaseCount('order_items', 0);

        $this->actingAs($supervisor)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '3',
            'unit_price' => '7.0000',
            'override_reason' => 'Promoción autorizada por supervisión',
            'notes' => null,
        ])->assertRedirect(route('orders.show', $order));

        $item = OrderItem::query()->sole();
        $this->assertSame(OrderPriceSource::Override, $item->price_source);
        $this->assertSame('10.0000', $item->standard_unit_price);
        $this->assertSame('7.0000', $item->unit_price);
        $this->assertSame($supervisor->id, $item->price_overridden_by);
        $this->assertSame('Promoción autorizada por supervisión', $item->override_reason);
    }

    public function test_confirmation_requires_items_locks_order_and_allows_bodeguero_to_view_it(): void
    {
        $user = User::factory()->preventista()->create();
        $bodeguero = User::factory()->create();
        $order = Order::factory()->create(['salesperson_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('orders.confirm', $order))
            ->assertSessionHasErrors(['items' => 'Agrega al menos un producto antes de confirmar el pedido.']);
        [, $unit] = $this->productWithPresentations();
        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '4',
            'notes' => null,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame($user->id, $order->confirmed_by);
        $this->actingAs($user)->put(route('orders.update', $order), [
            'payment_term' => PaymentTerm::Credit->value,
            'requested_delivery_date' => null,
            'notes' => 'No debe cambiar',
        ])->assertForbidden();
        $this->actingAs($bodeguero)->get(route('orders.show', $order))->assertOk()->assertSee($order->order_number);

        $draft = Order::factory()->create();
        $this->actingAs($bodeguero)->get(route('orders.show', $draft))->assertForbidden();
    }

    public function test_supervisor_can_cancel_and_reopen_with_complete_history(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $order = Order::factory()->confirmed()->create(['created_by' => $supervisor->id]);

        $this->actingAs($supervisor)->post(route('orders.cancel', $order), [
            'reason' => 'El cliente cerró temporalmente.',
        ])->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame('El cliente cerró temporalmente.', $order->cancellation_reason);

        $this->actingAs($supervisor)->post(route('orders.reopen', $order), [
            'reason' => 'El cliente confirmó que sí recibirá.',
        ])->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Draft, $order->refresh()->status);
        $this->assertNull($order->confirmed_at);
        $this->assertNull($order->cancelled_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Confirmed->value,
            'to_status' => OrderStatus::Cancelled->value,
            'reason' => 'El cliente cerró temporalmente.',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Cancelled->value,
            'to_status' => OrderStatus::Draft->value,
            'reason' => 'El cliente confirmó que sí recibirá.',
        ]);
    }

    public function test_order_page_escapes_snapshots_and_shows_conversion_before_confirmation(): void
    {
        $user = User::factory()->preventista()->create();
        $attack = "<script>alert('xss')</script>";
        $order = Order::factory()->create([
            'salesperson_id' => $user->id,
            'created_by' => $user->id,
            'customer_name' => "Pulpería {$attack}",
            'customer_address' => "Calle principal {$attack}",
            'notes' => "Nota {$attack}",
        ]);
        [$product, $unit] = $this->productWithPresentations();
        $product->update(['name' => "Galletas {$attack}"]);
        $order->statusHistory()->create([
            'from_status' => null,
            'to_status' => OrderStatus::Draft,
            'changed_by' => $user->id,
            'reason' => "Historial {$attack}",
        ]);
        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '30',
            'notes' => "Línea {$attack}",
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee($attack, false)
            ->assertSee('Sugerencias de conversión')
            ->assertSee('1</strong> × Caja 24', false)
            ->assertSee('6</strong> × Unidad', false)
            ->assertSee('nunca modifica el pedido automáticamente');
    }

    public function test_scoped_binding_returns_404_for_item_from_another_order(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $firstOrder = Order::factory()->create();
        $secondOrder = Order::factory()->create();
        $item = OrderItem::factory()->for($secondOrder)->create();

        $this->actingAs($supervisor)->delete(route('orders.items.destroy', [$firstOrder, $item]))->assertNotFound();

        $this->assertModelExists($item);
    }

    public function test_customer_product_presentation_and_price_changes_do_not_rewrite_order_history(): void
    {
        [$user, $customer, , $stop] = $this->routeContext();
        [$product, $unit] = $this->productWithPresentations();
        $originalCustomerName = $customer->business_name;
        $originalAddress = $customer->address;
        $originalProductName = $product->name;
        $originalSku = $product->sku;

        $this->actingAs($user)->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $user->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
        ])->assertRedirect();
        $order = Order::query()->sole();
        $this->actingAs($user)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $unit->id,
            'quantity' => '2',
            'notes' => null,
        ])->assertRedirect();

        $customer->update(['business_name' => 'Nombre nuevo', 'address' => 'Dirección nueva']);
        $product->update(['name' => 'Producto nuevo', 'sku' => 'SKU-NUEVO']);
        $unit->update(['name' => 'Blíster', 'sale_price' => '99.0000']);
        $this->actingAs($user)->post(route('orders.confirm', $order))->assertRedirect();

        $item = OrderItem::query()->sole();
        $this->assertSame($originalCustomerName, $order->refresh()->customer_name);
        $this->assertSame($originalAddress, $order->customer_address);
        $this->assertSame($originalProductName, $item->product_name);
        $this->assertSame($originalSku, $item->product_sku);
        $this->assertSame('Unidad', $item->presentation_name);
        $this->assertSame('10.0000', $item->unit_price);
        $this->assertSame('20.0000', $order->total);
    }

    /** @return array{User, Customer, SalesRoute, RouteStop} */
    private function routeContext(): array
    {
        $user = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        $salesRoute = SalesRoute::factory()->for($user, 'salesperson')->create();
        $stop = RouteStop::factory()->for($salesRoute)->for($customer)->create([
            'visit_day' => Weekday::Monday,
            'visit_order' => 2,
        ]);

        return [$user, $customer, $salesRoute, $stop];
    }

    /** @return array{Product, ProductPresentation, ProductPresentation} */
    private function productWithPresentations(): array
    {
        $baseUnit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'symbol' => 'ud']);
        $product = Product::factory()->for($baseUnit, 'baseUnit')->create([
            'name' => 'Galletas Clásicas',
            'sku' => fake()->unique()->bothify('GAL-###'),
        ]);
        $unit = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '10.0000',
        ]);
        $box = ProductPresentation::factory()->for($product)->create([
            'name' => 'Caja 24',
            'conversion_factor' => '24.000000',
            'sale_price' => '240.0000',
        ]);

        return [$product, $unit, $box];
    }
}
