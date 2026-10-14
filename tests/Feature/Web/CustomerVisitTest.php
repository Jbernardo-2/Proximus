<?php

namespace Tests\Feature\Web;

use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerVisit;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use App\Services\Reports\FieldVisitsReport;
use App\Services\Reports\ReportFilters;
use App\SettlementMethod;
use App\VisitLocationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerVisitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_an_order_records_where_and_when_it_was_taken(): void
    {
        [$preventista, $customer, $stop] = $this->assignedCustomer();
        $takenAt = now()->subHours(3)->startOfSecond();

        $this->actingAs($preventista)->post(route('orders.store'), [
            ...$this->orderPayload($customer, $stop),
            'location' => [
                'latitude' => '14.0731000',
                'longitude' => '-87.1921000',
                'accuracy' => '8.4',
                'captured_at' => $takenAt->toIso8601String(),
                'status' => 'captured',
            ],
        ])->assertSessionHasNoErrors();

        $visit = CustomerVisit::query()->sole();
        $order = Order::query()->sole();

        $this->assertSame(CustomerVisitType::OrderTaken, $visit->type);
        $this->assertSame($preventista->id, $visit->user_id);
        $this->assertSame($customer->id, $visit->customer_id);
        $this->assertTrue($visit->subject->is($order));
        $this->assertSame(VisitLocationStatus::Captured, $visit->location_status);
        $this->assertSame('14.0731000', $visit->latitude);
        $this->assertSame('8.4', $visit->accuracy_meters);
        $this->assertTrue($visit->occurred_at->equalTo($takenAt));
        $this->assertEqualsWithDelta(92, $visit->distance_meters, 1);
    }

    public function test_an_order_is_saved_even_when_the_location_is_denied_missing_or_invalid(): void
    {
        [$preventista, $customer, $stop] = $this->assignedCustomer();

        $this->actingAs($preventista)->post(route('orders.store'), [
            ...$this->orderPayload($customer, $stop),
            'location' => ['status' => 'denied', 'captured_at' => 'no es una fecha'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($preventista)->post(route('orders.store'), $this->orderPayload($customer, $stop))
            ->assertSessionHasNoErrors();
        $this->actingAs($preventista)->post(route('orders.store'), [
            ...$this->orderPayload($customer, $stop),
            'location' => ['latitude' => '914', 'longitude' => 'abc', 'captured_at' => now()->addDays(3)->toIso8601String()],
        ])->assertSessionHasNoErrors();

        $visits = CustomerVisit::query()->oldest('id')->get();

        $this->assertSame(3, Order::query()->count());
        $this->assertSame(
            [VisitLocationStatus::Denied, VisitLocationStatus::Unavailable, VisitLocationStatus::Unavailable],
            $visits->pluck('location_status')->all(),
        );
        $this->assertTrue($visits->every(fn (CustomerVisit $visit): bool => $visit->latitude === null && $visit->distance_meters === null));
        $this->assertTrue($visits->last()->occurred_at->lessThanOrEqualTo(now()));
    }

    public function test_delivery_and_payment_are_located_without_manual_coordinates(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        $order = Order::factory()->create(['status' => OrderStatus::InTransit]);
        CustomerCreditProfile::factory()->create(['customer_id' => $order->customer_id]);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $order->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => '50.0000',
            'balance_due' => '50.0000',
        ]);
        $location = ['latitude' => '14.1000000', 'longitude' => '-87.2000000', 'accuracy' => '15', 'status' => 'captured'];

        $this->actingAs($driver)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('name="location[latitude]"', false)
            ->assertDontSee('name="latitude"', false);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [['method' => SettlementMethod::Cash->value, 'amount' => '50', 'reference' => null]],
            'location' => $location,
        ])->assertSessionHasNoErrors();

        $visit = CustomerVisit::query()->sole();
        $this->assertSame(CustomerVisitType::DeliveryPayment, $visit->type);
        $this->assertSame($order->customer_id, $visit->customer_id);
        $this->assertSame('-87.2000000', $visit->longitude);
        $this->assertTrue($visit->subject->is($runOrder->settlement));
    }

    public function test_a_visit_without_an_order_is_recorded_with_its_reason(): void
    {
        [$preventista, $customer] = $this->assignedCustomer();

        $this->actingAs($preventista)->post(route('customers.visits.store', $customer), ['notes' => 'Sin motivo'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($preventista)->post(route('customers.visits.store', $customer), [
            'reason' => CustomerVisitReason::HasStock->value,
            'notes' => 'Volver el viernes.',
            'location' => ['latitude' => '14.0722750', 'longitude' => '-87.1921360', 'status' => 'captured'],
        ])->assertRedirect(route('customers.show', $customer));

        $visit = CustomerVisit::query()->sole();
        $this->assertSame(CustomerVisitType::NoSale, $visit->type);
        $this->assertSame(CustomerVisitReason::HasStock, $visit->reason);
        $this->assertSame(0, $visit->distance_meters);

        $this->actingAs(User::factory()->repartidor()->create())
            ->post(route('customers.visits.store', $customer), ['reason' => CustomerVisitReason::Other->value])
            ->assertForbidden();
    }

    public function test_the_api_accepts_offline_visits_once_and_keeps_the_device_time(): void
    {
        [$preventista, $customer] = $this->assignedCustomer();
        Sanctum::actingAs($preventista, ['orders:manage']);
        $visitedAt = now()->subDays(2)->startOfSecond();
        $payload = [
            'client_reference' => 'telefono-7:visita-0001',
            'reason' => CustomerVisitReason::BusinessClosed->value,
            'location' => ['latitude' => 14.08, 'longitude' => -87.19, 'accuracy' => 30, 'captured_at' => $visitedAt->toIso8601String()],
        ];

        $first = $this->postJson("/api/v1/customers/{$customer->id}/visits", $payload)
            ->assertCreated()
            ->assertJsonPath('data.location_status', 'captured')
            ->assertJsonPath('data.reason', 'business_closed');
        $this->postJson("/api/v1/customers/{$customer->id}/visits", $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $visit = CustomerVisit::query()->sole();
        $this->assertTrue($visit->occurred_at->equalTo($visitedAt));
        $this->assertTrue($visit->created_at->greaterThan($visitedAt->addDay()));
    }

    public function test_supervisor_reviews_movements_and_flags_visits_far_from_the_customer(): void
    {
        $preventista = User::factory()->preventista()->create(['name' => 'Ana Preventista']);
        $customer = Customer::factory()->create(['business_name' => 'Pulpería La Estrella']);
        CustomerVisit::factory()->create(['user_id' => $preventista->id, 'customer_id' => $customer->id, 'distance_meters' => 12]);
        CustomerVisit::factory()->create(['user_id' => $preventista->id, 'customer_id' => $customer->id, 'distance_meters' => 950]);
        CustomerVisit::factory()->create([
            'user_id' => $preventista->id,
            'type' => CustomerVisitType::NoSale,
            'reason' => CustomerVisitReason::BusinessClosed,
            'location_status' => VisitLocationStatus::Denied,
            'latitude' => null,
            'longitude' => null,
            'distance_meters' => null,
        ]);
        CustomerVisit::factory()->create(['occurred_at' => now()->subDays(40)]);
        $filters = new ReportFilters('custom', CarbonImmutable::today()->subDays(6), CarbonImmutable::today());

        $summary = collect(app(FieldVisitsReport::class)->summary($filters))->keyBy('label');
        $this->assertSame(3, $summary['Visitas registradas']['value']);
        $this->assertSame(66.7, $summary['Con ubicación']['value']);
        $this->assertSame(1, $summary['Lejos del cliente']['value']);
        $this->assertSame(1, $summary['Visitas sin pedido']['value']);

        $this->actingAs(User::factory()->supervisor()->create())
            ->get(route('reports.show', 'visitas-campo'))
            ->assertOk()
            ->assertSee('Ana Preventista')
            ->assertSee('Pulpería La Estrella')
            ->assertSee('Permiso negado')
            ->assertSee('Negocio cerrado')
            ->assertSee('https://www.google.com/maps?q=14.072275,-87.192136', false);
    }

    /** @return array{0: User, 1: Customer, 2: RouteStop} */
    private function assignedCustomer(): array
    {
        $preventista = User::factory()->preventista()->create();
        $customer = Customer::factory()->create(['latitude' => '14.0722750', 'longitude' => '-87.1921360']);
        $route = SalesRoute::factory()->create(['salesperson_id' => $preventista->id]);
        $stop = RouteStop::factory()->for($route)->for($customer)->create();

        return [$preventista, $customer, $stop];
    }

    /** @return array<string, mixed> */
    private function orderPayload(Customer $customer, RouteStop $stop): array
    {
        return [
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
        ];
    }
}
