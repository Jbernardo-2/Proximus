<?php

namespace Tests\Feature\Web;

use App\DeliveryOrderStatus;
use App\DeliveryOutcomeReason;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderStatus;
use App\Services\Reports\ExecutiveSummaryReport;
use App\Services\Reports\FulfillmentReport;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\SalesByProductReport;
use App\Services\Reports\SalesFacts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportCenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_concepts_are_kept_apart_and_add_up(): void
    {
        $this->seedSales();

        $totals = app(SalesFacts::class)->totals($this->filters());

        $this->assertSame(450.0, $totals['requested']);
        $this->assertSame(320.0, $totals['delivered']);
        $this->assertSame(80.0, $totals['lost']);
        $this->assertSame(50.0, $totals['in_process']);
        $this->assertSame(80.0, $totals['cancelled']);
        $this->assertSame(250.0, $totals['collected']);
        $this->assertSame(70.0, $totals['credit']);
        $this->assertSame($totals['requested'], $totals['delivered'] + $totals['lost'] + $totals['in_process']);
        $this->assertSame(
            [4, 1, 1, 1, 1, 2],
            [$totals['orders'], $totals['delivered_orders'], $totals['partial_orders'], $totals['failed_orders'], $totals['cancelled_orders'], $totals['customers']],
        );
    }

    public function test_executive_summary_compares_against_the_previous_period(): void
    {
        $this->seedSales();

        $summary = collect(app(ExecutiveSummaryReport::class)->summary($this->filters()))->keyBy('label');

        $this->assertSame(320.0, $summary['Venta entregada']['value']);
        $this->assertSame(500.0, $summary['Venta entregada']['previous']);
        $this->assertSame(80.0, $summary['Venta perdida']['value']);
        $this->assertSame(160.0, $summary['Ticket promedio']['value']);
        $this->assertSame(80.0, $summary['Cumplimiento de pedidos']['value']);
        $this->assertSame(33.3, $summary['Entregas completas']['value']);
    }

    public function test_filtering_by_delivery_date_only_counts_closed_orders_delivered_in_the_period(): void
    {
        $this->seedSales();

        $totals = app(SalesFacts::class)->totals($this->filters(['basis' => 'delivery']));

        $this->assertSame(400.0, $totals['requested']);
        $this->assertSame(320.0, $totals['delivered']);
        $this->assertSame(0.0, $totals['in_process']);
        $this->assertSame(0, $totals['cancelled_orders']);
    }

    public function test_product_and_fulfillment_reports_follow_each_unit_from_request_to_delivery(): void
    {
        $this->seedSales();
        $filters = $this->filters();

        $product = app(SalesByProductReport::class);
        $rice = $product->mapRow($product->query($filters)->get()->firstWhere('product_name', 'Arroz precocido'));

        $this->assertSame(24.0, (float) $rice['requested_quantity']);
        $this->assertSame(22.0, (float) $rice['delivered_quantity']);
        $this->assertSame(220.0, (float) $rice['delivered']);
        $this->assertSame(10.0, $rice['average_price']);
        $this->assertSame(1.0, (float) $rice['returned_quantity']);
        $this->assertSame(1.0, (float) $rice['damaged_quantity']);
        $this->assertSame(91.7, $rice['fulfillment']);

        $stages = collect(app(FulfillmentReport::class)->summary($filters))->keyBy('label');

        $this->assertSame(400.0, $stages['Solicitado']['value']);
        $this->assertSame(400.0, $stages['Cargado']['value']);
        $this->assertSame(320.0, $stages['Entregado']['value']);
        $this->assertSame(80.0, $stages['Venta perdida']['value']);
    }

    public function test_supervisor_can_open_print_and_download_every_report(): void
    {
        $this->seedSales();
        $supervisor = User::factory()->supervisor()->create();
        $period = ['period' => 'custom', 'from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];

        $this->actingAs($supervisor)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Resumen ejecutivo')
            ->assertSee('Cumplimiento y venta perdida');

        foreach (app(ReportRegistry::class)->all() as $report) {
            $this->actingAs($supervisor)->get(route('reports.show', ['report' => $report->key(), ...$period]))
                ->assertOk()
                ->assertSee($report->title());
            $this->actingAs($supervisor)->get(route('reports.print', ['report' => $report->key(), ...$period]))
                ->assertOk()
                ->assertSee('Generado el');
        }

        $this->actingAs($supervisor)->get(route('reports.show', ['report' => 'resumen-ejecutivo', ...$period]))
            ->assertSee('HNL 450.00')
            ->assertSee('HNL 320.00')
            ->assertSee('vs. período anterior');
        $this->actingAs($supervisor)->get(route('reports.show', ['report' => 'ventas-cliente', ...$period]))
            ->assertSee('Pulpería La Estrella')
            ->assertSee('HNL 220.00');

        $pdf = $this->actingAs($supervisor)->get(route('reports.download', ['report' => 'ventas-detalle', ...$period]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->actingAs($supervisor)->get(route('reports.show', 'no-existe'))->assertNotFound();
    }

    public function test_detail_report_filters_searches_sorts_and_exports(): void
    {
        ['route' => $route, 'partial' => $partial, 'draft' => $draft] = $this->seedSales();
        $supervisor = User::factory()->supervisor()->create();
        $period = ['period' => 'custom', 'from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];

        $csv = $this->actingAs($supervisor)
            ->get(route('reports.export', ['report' => 'ventas-detalle', ...$period]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Solicitado,Entregado,Cobrado,Crédito', $csv);
        $this->assertStringContainsString($partial->order_number, $csv);
        $this->assertStringContainsString('240,220,150,70', $csv);
        $this->assertStringContainsString('Cliente no pudo pagar', $csv);
        $this->assertStringNotContainsString($draft->order_number, $csv);
        $this->assertSame(6, substr_count(trim($csv), "\n") + 1);

        $filtered = $this->actingAs($supervisor)
            ->get(route('reports.export', ['report' => 'ventas-detalle', ...$period, 'route' => $route->id]))
            ->streamedContent();
        $this->assertSame(2, substr_count(trim($filtered), "\n") + 1);

        $this->actingAs($supervisor)
            ->get(route('reports.show', ['report' => 'ventas-detalle', ...$period, 'search' => 'Estrella', 'sort' => 'delivered', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee($partial->order_number)
            ->assertSee('1 registro');
    }

    public function test_reports_are_limited_to_management(): void
    {
        foreach ([User::factory()->preventista()->create(), User::factory()->repartidor()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.show', 'resumen-ejecutivo'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.export', 'ventas-detalle'))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    /** @param array<string, string> $overrides */
    private function filters(array $overrides = []): ReportFilters
    {
        return new ReportFilters(
            period: 'custom',
            from: CarbonImmutable::today()->subDays(6),
            to: CarbonImmutable::today(),
            basis: $overrides['basis'] ?? 'order',
        );
    }

    /** @return array{route: SalesRoute, partial: Order, draft: Order} */
    private function seedSales(): array
    {
        $route = SalesRoute::factory()->create(['name' => 'Ruta Norte']);
        $run = DeliveryRun::factory()->create(['status' => DeliveryRunStatus::InTransit]);
        $partial = $this->order(OrderStatus::PartiallyDelivered, '240', [
            'customer_name' => 'Pulpería La Estrella',
            'sales_route_id' => $route->id,
            'route_name' => $route->name,
        ]);
        $this->attempt($run, $partial, 1, DeliveryOrderStatus::PartiallyDelivered, '220', '150', '70', DeliveryOutcomeReason::CannotPay);
        $this->line($partial, 'Arroz precocido', '24', '22', ['returned_quantity' => '1', 'damaged_quantity' => '1']);

        $complete = $this->order(OrderStatus::Delivered, '100');
        $this->attempt($run, $complete, 2, DeliveryOrderStatus::Delivered, '100', '100', '0');
        $this->line($complete, 'Frijol rojo', '10', '10');

        $failed = $this->order(OrderStatus::NotDelivered, '60');
        $this->attempt($run, $failed, 3, DeliveryOrderStatus::NotDelivered, '0', '0', '0', DeliveryOutcomeReason::BusinessClosed);
        $this->line($failed, 'Azúcar', '6', '0', ['returned_quantity' => '6']);

        $this->order(OrderStatus::Confirmed, '50');
        $this->order(OrderStatus::Cancelled, '80');
        $draft = $this->order(OrderStatus::Draft, '999');

        $earlier = $this->order(OrderStatus::Delivered, '500', ['order_date' => today()->subDays(8)->toDateString()]);
        $this->attempt($run, $earlier, 4, DeliveryOrderStatus::Delivered, '500', '500', '0', null, now()->subDays(8));

        return compact('route', 'partial', 'draft');
    }

    /** @param array<string, mixed> $attributes */
    private function order(OrderStatus $status, string $total, array $attributes = []): Order
    {
        return Order::factory()->create(['status' => $status, 'subtotal' => $total, 'total' => $total, ...$attributes]);
    }

    private function attempt(
        DeliveryRun $run,
        Order $order,
        int $visitOrder,
        DeliveryOrderStatus $status,
        string $delivered,
        string $collected,
        string $credit,
        ?DeliveryOutcomeReason $reason = null,
        mixed $completedAt = null,
    ): DeliveryRunOrder {
        return DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $order->id,
            'visit_order' => $visitOrder,
            'status' => $status,
            'outcome_status' => $status,
            'requested_total' => $order->total,
            'delivered_total' => $delivered,
            'collected_total' => $collected,
            'balance_due' => $credit,
            'outcome_reason' => $reason,
            'completed_at' => $completedAt ?? now(),
        ]);
    }

    /** @param array<string, string> $outcome */
    private function line(Order $order, string $name, string $requested, string $delivered, array $outcome = []): void
    {
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => $name,
            'quantity' => $requested,
            'base_quantity' => $requested,
            'unit_price' => '10.0000',
            'line_total' => bcmul($requested, '10', 4),
        ]);
        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $order->refresh()->load('deliveryRunOrders')->deliveryRunOrders->last()->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_presentation_id' => $item->product_presentation_id,
            'product_name' => $name,
            'unit_price' => '10.0000',
            'requested_quantity' => $requested,
            'prepared_quantity' => $requested,
            'loaded_quantity' => $requested,
            'delivered_quantity' => $delivered,
            'delivered_base_quantity' => $delivered,
            'delivered_line_total' => bcmul($delivered, '10', 4),
            ...$outcome,
        ]);
    }
}
