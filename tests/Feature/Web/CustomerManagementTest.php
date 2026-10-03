<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preventista_can_create_customer_and_render_details(): void
    {
        $user = User::factory()->preventista()->create();

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
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('success', 'Cliente creado correctamente.');
        $this->assertSame('CLI-001', $customer->code);
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

    public function test_customer_creation_returns_validation_messages_for_missing_identity_and_location(): void
    {
        $user = User::factory()->preventista()->create();

        $this->actingAs($user)
            ->post(route('customers.store'), ['is_active' => '1'])
            ->assertSessionHasErrors([
                'code' => 'El campo código es obligatorio.',
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
        $user = User::factory()->preventista()->create();
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
        $user = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        RouteStop::factory()->for($customer)->for(SalesRoute::factory())->create();

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHas('error', 'No se puede eliminar un cliente asignado a rutas. Puedes desactivarlo o retirar primero sus visitas.');

        $this->assertModelExists($customer);
    }
}
