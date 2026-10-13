<?php

namespace Tests\Feature\Web;

use App\Models\CompanyProfile;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\SalesRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RouteReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_active_role_can_open_the_report_index(): void
    {
        $users = [
            User::factory()->admin()->create(),
            User::factory()->supervisor()->create(),
            User::factory()->preventista()->create(),
            User::factory()->create(),
            User::factory()->repartidor()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('route-reports.index'))->assertOk();
        }
    }

    public function test_print_report_consolidates_products_and_keeps_routes_separate(): void
    {
        $fixture = $this->createReportFixture();
        CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID)->update([
            'commercial_name' => 'Distribuidora La Prueba',
        ]);

        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $fixture['firstRunOrder']->id,
            'product_id' => $fixture['firstItem']->product_id,
            'product_presentation_id' => $fixture['firstItem']->product_presentation_id,
            'product_sku' => 'ARROZ-001',
            'product_name' => 'Arroz blanco',
            'presentation_name' => 'Fardo',
            'base_unit_symbol' => 'lb',
            'requested_quantity' => '3.000000',
            'requested_base_quantity' => '30.000000',
        ]);

        $response = $this->actingAs($fixture['supervisor'])
            ->get(route('route-reports.print', [$fixture['run'], $fixture['route']]));

        $response->assertOk()
            ->assertSee('Distribuidora La Prueba')
            ->assertSee($fixture['run']->run_number)
            ->assertSee($fixture['route']->name)
            ->assertSee('Cliente Ruta Norte')
            ->assertSee('Arroz blanco')
            ->assertSee('5')
            ->assertDontSee('Cliente Ruta Sur')
            ->assertSee('Imprimir ahora')
            ->assertSee('Descargar PDF');
    }

    public function test_download_returns_a_pdf_attachment(): void
    {
        $fixture = $this->createReportFixture();

        $response = $this->actingAs($fixture['supervisor'])
            ->get(route('route-reports.download', [$fixture['run'], $fixture['route']]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    public function test_preventista_and_driver_cannot_open_reports_outside_their_assignment(): void
    {
        $fixture = $this->createReportFixture();
        $otherSalesperson = User::factory()->preventista()->create();
        $otherDriver = User::factory()->repartidor()->create();

        $this->actingAs($fixture['salesperson'])
            ->get(route('route-reports.print', [$fixture['run'], $fixture['route']]))
            ->assertOk();
        $this->actingAs($otherSalesperson)
            ->get(route('route-reports.print', [$fixture['run'], $fixture['route']]))
            ->assertForbidden();
        $this->actingAs($fixture['driver'])
            ->get(route('route-reports.print', [$fixture['run'], $fixture['route']]))
            ->assertOk();
        $this->actingAs($otherDriver)
            ->get(route('route-reports.print', [$fixture['run'], $fixture['route']]))
            ->assertForbidden();
    }

    public function test_report_index_only_lists_routes_available_to_the_user(): void
    {
        $fixture = $this->createReportFixture();
        $otherSalesperson = User::factory()->preventista()->create();

        $this->actingAs($fixture['salesperson'])
            ->get(route('route-reports.index'))
            ->assertOk()
            ->assertSee('Ruta Norte')
            ->assertDontSee('Ruta Sur');

        $this->actingAs($otherSalesperson)
            ->get(route('route-reports.index'))
            ->assertOk()
            ->assertDontSee($fixture['run']->run_number);
    }

    public function test_report_is_forbidden_when_the_route_is_not_part_of_the_run(): void
    {
        $fixture = $this->createReportFixture();
        $unrelatedRoute = SalesRoute::factory()->create();

        $this->actingAs($fixture['supervisor'])
            ->get(route('route-reports.print', [$fixture['run'], $unrelatedRoute]))
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function createReportFixture(): array
    {
        $supervisor = User::factory()->supervisor()->create();
        $salesperson = User::factory()->preventista()->create();
        $driver = User::factory()->repartidor()->create();
        $route = SalesRoute::factory()->create([
            'code' => 'NORTE-01',
            'name' => 'Ruta Norte',
            'salesperson_id' => $salesperson->id,
            'driver_id' => $driver->id,
        ]);
        $otherRoute = SalesRoute::factory()->create([
            'code' => 'SUR-01',
            'name' => 'Ruta Sur',
        ]);
        $run = DeliveryRun::factory()->create([
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'created_by' => $supervisor->id,
        ]);
        $firstOrder = Order::factory()->confirmed()->create([
            'sales_route_id' => $route->id,
            'salesperson_id' => $salesperson->id,
            'route_code' => $route->code,
            'route_name' => $route->name,
            'salesperson_name' => $salesperson->name,
            'customer_name' => 'Cliente Ruta Norte',
            'customer_address' => 'Barrio El Centro',
            'total' => '750.0000',
        ]);
        $firstRunOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $firstOrder->id,
            'visit_order' => 1,
            'requested_total' => '750.0000',
        ]);
        $firstItem = DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $firstRunOrder->id,
            'product_sku' => 'ARROZ-001',
            'product_name' => 'Arroz blanco',
            'presentation_name' => 'Fardo',
            'base_unit_symbol' => 'lb',
            'requested_quantity' => '2.000000',
            'requested_base_quantity' => '20.000000',
        ]);
        $secondOrder = Order::factory()->confirmed()->create([
            'sales_route_id' => $otherRoute->id,
            'route_code' => $otherRoute->code,
            'route_name' => $otherRoute->name,
            'customer_name' => 'Cliente Ruta Sur',
            'total' => '300.0000',
        ]);
        $secondRunOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $secondOrder->id,
            'visit_order' => 2,
            'requested_total' => '300.0000',
        ]);
        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $secondRunOrder->id,
            'product_name' => 'Producto exclusivo del sur',
        ]);

        return compact(
            'supervisor',
            'salesperson',
            'driver',
            'route',
            'otherRoute',
            'run',
            'firstRunOrder',
            'firstItem',
        );
    }
}
