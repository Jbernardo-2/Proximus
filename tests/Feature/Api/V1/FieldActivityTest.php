<?php

namespace Tests\Feature\Api\V1;

use App\CustomerVisitType;
use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_supervisor_sees_each_route_and_each_run_on_the_road_today(): void
    {
        $seller = User::factory()->preventista()->create(['name' => 'Ana Ruta']);
        $route = SalesRoute::factory()->for($seller, 'salesperson')->create();
        [$sold, $closed, $pending] = Customer::factory()->count(3)->create()->all();
        $stops = collect([$sold, $closed, $pending])->map(fn (Customer $customer, int $index) => RouteStop::factory()->for($route)->for($customer)->create(['visit_day' => now()->dayOfWeekIso, 'visit_order' => $index + 1]));
        Order::factory()->create(['customer_id' => $sold->id, 'route_stop_id' => $stops[0]->id, 'salesperson_id' => $seller->id, 'order_date' => today(), 'status' => OrderStatus::Confirmed, 'total' => '150.0000']);
        CustomerVisit::factory()->create(['customer_id' => $closed->id, 'user_id' => $seller->id, 'type' => CustomerVisitType::NoSale, 'occurred_at' => now()]);
        User::factory()->preventista()->create(['name' => 'Sin ruta hoy']);

        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'driver_name' => 'Carlos', 'status' => DeliveryRunStatus::InTransit, 'departed_at' => now()->subHours(2)]);
        DeliveryRunOrder::factory()->create(['delivery_run_id' => $run->id, 'visit_order' => 1, 'status' => DeliveryOrderStatus::Delivered, 'completed_at' => now()->subHour()]);
        DeliveryRunOrder::factory()->create(['delivery_run_id' => $run->id, 'visit_order' => 2, 'status' => DeliveryOrderStatus::Loaded]);
        DeliveryRun::factory()->create(['status' => DeliveryRunStatus::Settled]);

        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());

        $this->getJson('/api/v1/field-activity')
            ->assertOk()
            ->assertJsonCount(1, 'data.salespeople')
            ->assertJsonPath('data.salespeople.0.name', 'Ana Ruta')
            ->assertJsonPath('data.salespeople.0.stops', 3)
            ->assertJsonPath('data.salespeople.0.with_order', 1)
            ->assertJsonPath('data.salespeople.0.no_sale', 1)
            ->assertJsonPath('data.salespeople.0.pending', 1)
            ->assertJsonPath('data.salespeople.0.sold', '150.0000')
            ->assertJsonCount(1, 'data.runs')
            ->assertJsonPath('data.runs.0.driver_name', 'Carlos')
            ->assertJsonPath('data.runs.0.stops', 2)
            ->assertJsonPath('data.runs.0.completed_stops', 1);
        $this->getJson("/api/v1/field-activity/salespeople/{$seller->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.stops')
            ->assertJsonPath('data.stops.0.status', 'order_taken')
            ->assertJsonPath('data.stops.1.status', 'no_sale');
        $this->getJson("/api/v1/field-activity/salespeople/{$driver->id}")->assertNotFound();

        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $this->getJson('/api/v1/field-activity')->assertForbidden();
    }
}
