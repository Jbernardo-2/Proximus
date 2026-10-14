<?php

namespace Tests\Feature\Web;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preventista_can_create_customer_and_render_details(): void
    {
        $user = User::factory()->preventista()->create();
        $route = SalesRoute::factory()->create(['salesperson_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'code' => 'cli-001',
            'business_name' => 'Pulpería La Bendición',
            'business_type' => 'Pulpería',
            'contact_name' => 'María López',
            'phone' => '2222-2222',
            'whatsapp' => '9999-9999',
            'email' => 'maria@example.com',
            'address' => 'Barrio El Centro, local 4',
            'reference' => 'Frente a la escuela',
            'latitude' => '14.0723000',
            'longitude' => '-87.1921000',
            'notes' => 'Visitar por la mañana',
            'is_active' => '1',
        ]);

        $customer = Customer::query()->sole();
        $response
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Cliente creado correctamente.');
        $this->assertSame('CLI-001', $customer->code);
        $this->assertDatabaseHas('route_stops', ['customer_id' => $customer->id, 'sales_route_id' => $route->id, 'visit_day' => now()->dayOfWeekIso, 'is_active' => true]);
        $this->assertSame('14.0723000', $customer->latitude);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'business_name' => 'Pulpería La Bendición',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Pulpería La Bendición')
            ->assertSee('Frente a la escuela');
    }

    public function test_supervisor_can_apply_customer_payment_to_an_open_receivable(): void
    {
        $user = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $runOrder = DeliveryRunOrder::factory()->for($order)->create();
        $receivable = CustomerReceivable::factory()->create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'delivery_run_order_id' => $runOrder->id,
            'original_amount' => '800.0000',
            'outstanding_amount' => '800.0000',
            'status' => CustomerReceivableStatus::Open,
            'created_by' => $user->id,
        ]);
        CustomerAccountEntry::factory()->create([
            'customer_id' => $customer->id,
            'type' => CustomerAccountEntryType::Sale,
            'status' => CustomerAccountEntryStatus::Active,
            'order_id' => $order->id,
            'delivery_run_order_id' => $runOrder->id,
            'debit_amount' => '800.0000',
            'credit_amount' => '0.0000',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('customers.payments.store', $customer), [
            'method' => PaymentMethod::Cash->value,
            'amount' => '500',
            'reference' => null,
            'notes' => 'Abono parcial en tienda.',
            'allocations' => [[
                'receivable_id' => $receivable->id,
                'amount' => '500',
            ]],
        ]);
        $response
            ->assertSessionHasNoErrors();

        $payment = CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment->value)
            ->sole();
        $response->assertRedirect(route('customers.payments.receipt.show', [$customer, $payment]));
        $response->assertSessionHas('success', "Abono {$payment->receipt_number} registrado correctamente.");
        $this->assertSame('300.0000', $receivable->refresh()->outstanding_amount);
        $this->assertSame(CustomerReceivableStatus::PartiallyPaid, $receivable->status);
        $this->assertSame('500.0000', $payment->credit_amount);
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertDatabaseHas('customer_receivable_allocations', [
            'customer_receivable_id' => $receivable->id,
            'customer_account_entry_id' => $payment->id,
            'amount' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('HNL 300.00')
            ->assertSee($payment->receipt_number)
            ->assertSee('Abono parcial en tienda.');
    }

    public function test_customer_form_offers_current_location_map_and_manual_fallback(): void
    {
        $user = User::factory()->supervisor()->create();

        $this->actingAs($user)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Usar mi ubicación')
            ->assertSee('data-location-map', false)
            ->assertSee('data-location-latitude', false)
            ->assertSee('Ver o escribir coordenadas manualmente');
    }

    public function test_customer_payment_cannot_be_applied_to_another_customers_debt(): void
    {
        $user = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $otherOrder = Order::factory()->for($otherCustomer)->create();
        $otherRunOrder = DeliveryRunOrder::factory()->for($otherOrder)->create();
        $otherReceivable = CustomerReceivable::factory()->create([
            'customer_id' => $otherCustomer->id,
            'order_id' => $otherOrder->id,
            'delivery_run_order_id' => $otherRunOrder->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('customers.payments.store', $customer), [
            'method' => PaymentMethod::Cash->value,
            'amount' => '25',
            'allocations' => [[
                'receivable_id' => $otherReceivable->id,
                'amount' => '25',
            ]],
        ])->assertSessionHasErrors('allocations.0.receivable_id');

        $this->assertDatabaseMissing('customer_account_entries', [
            'customer_id' => $customer->id,
            'type' => CustomerAccountEntryType::CustomerPayment->value,
        ]);
        $this->assertSame('100.0000', $otherReceivable->refresh()->outstanding_amount);
    }

    public function test_customer_creation_returns_validation_messages_for_missing_identity_and_location(): void
    {
        $user = User::factory()->preventista()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), ['is_active' => '1'])
            ->assertSessionDoesntHaveErrors('code')
            ->assertSessionHasErrors([
                'business_name' => 'El campo nombre del negocio es obligatorio.',
                'address' => 'El campo dirección es obligatorio.',
            ]);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_bodeguero_is_forbidden_from_customer_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
    }

    public function test_customer_index_escapes_dangerous_content(): void
    {
        $user = User::factory()->supervisor()->create();
        Customer::factory()->create([
            'business_name' => "Pulpería <script>alert('xss')</script>",
            'address' => "Calle <script>alert('address')</script>",
        ]);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertDontSee("<script>alert('address')</script>", false);
    }

    public function test_customer_assigned_to_a_route_cannot_be_deleted(): void
    {
        $user = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        RouteStop::factory()->for($customer)->for(SalesRoute::factory())->create();

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHas('error', 'No se puede eliminar un cliente con rutas, pedidos o historial financiero. Desactívalo para conservar la trazabilidad.');

        $this->assertModelExists($customer);
    }
}
