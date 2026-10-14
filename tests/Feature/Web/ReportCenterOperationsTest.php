<?php

namespace Tests\Feature\Web;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerCreditStatus;
use App\DeliveryOrderStatus;
use App\DeliveryOutcomeReason;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerCreditAuthorization;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerPaymentLine;
use App\Models\CustomerReceivable;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use App\PaymentMethod;
use App\Services\Reports\CreditExceptionsReport;
use App\Services\Reports\CreditUsageReport;
use App\Services\Reports\DriverPerformanceReport;
use App\Services\Reports\LoadReconciliationReport;
use App\Services\Reports\PaymentsReport;
use App\Services\Reports\ReceivablesAgingReport;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\RouteResultsReport;
use App\Services\Reports\RunSettlementsReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReportCenterOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_route_results_and_driver_performance_summarise_each_run(): void
    {
        $this->seedRun();

        $summary = $this->summary(RouteResultsReport::class);
        $this->assertSame(1, $summary['Jornadas']['value']);
        $this->assertSame(33.3, $summary['Entregas completas']['value']);
        $this->assertSame(400.0, $summary['Venta cargada']['value']);
        $this->assertSame(320.0, $summary['Venta entregada']['value']);
        $this->assertSame(70.0, $summary['Mercadería retornada']['value']);
        $this->assertSame(10.0, $summary['Dañado y faltante']['value']);

        $driver = $this->rows(DriverPerformanceReport::class)->sole();
        $this->assertSame('Carlos Repartidor', $driver['driver_name']);
        $this->assertSame([1, 3, 1, 1, 1], [(int) $driver['runs'], (int) $driver['completed_stops'], (int) $driver['delivered_stops'], (int) $driver['partial_stops'], (int) $driver['failed_stops']]);
        $this->assertSame(-10.0, (float) $driver['cash_difference']);
        $this->assertSame(1, (int) $driver['runs_with_difference']);
        $this->assertSame(70.0, (float) $driver['returned']);

        $reasons = app(DriverPerformanceReport::class)->charts($this->filters())[0];
        $this->assertEqualsCanonicalizing(['Cliente no pudo pagar', 'Negocio cerrado'], $reasons['labels']);
    }

    public function test_load_reconciliation_accounts_for_every_loaded_unit(): void
    {
        $this->seedRun();

        $summary = $this->summary(LoadReconciliationReport::class);
        $this->assertSame(400.0, $summary['Cargado']['value']);
        $this->assertSame(320.0, $summary['Entregado']['value']);
        $this->assertSame(70.0, $summary['Retornado']['value']);
        $this->assertSame(10.0, $summary['Dañado']['value']);
        $this->assertSame(0.0, $summary['Pendiente de conciliar']['value']);
        $this->assertSame(
            $summary['Cargado']['value'],
            $summary['Entregado']['value'] + $summary['Retornado']['value'] + $summary['Dañado']['value'] + $summary['Faltante']['value'],
        );

        $rice = $this->rows(LoadReconciliationReport::class)->firstWhere('product_name', 'Arroz precocido');
        $this->assertSame([24.0, 22.0, 1.0, 1.0, 0.0], [(float) $rice['loaded_quantity'], (float) $rice['delivered_quantity'], (float) $rice['returned_quantity'], (float) $rice['damaged_quantity'], (float) $rice['unexplained_quantity']]);
    }

    public function test_payments_report_separates_delivery_collections_account_payments_and_voids(): void
    {
        $this->seedRun();

        $summary = $this->summary(PaymentsReport::class);
        $this->assertSame(290.0, $summary['Total cobrado']['value']);
        $this->assertSame(250.0, $summary['Cobrado en entregas']['value']);
        $this->assertSame(40.0, $summary['Abonos a cuenta']['value']);
        $this->assertSame(45.0, $summary['Pagos anulados']['value']);
        $this->assertSame(1, $summary['Sin referencia']['value']);

        $rows = $this->rows(PaymentsReport::class);
        $this->assertCount(5, $rows);
        $this->assertSame(2, $rows->where('status', 'Anulado')->count());
        $this->assertSame(1, $rows->where('origin', 'Abono a cuenta')->where('status', 'Válido')->count());

        $transfers = app(PaymentsReport::class)->query($this->filters(['method' => PaymentMethod::BankTransfer->value]))->get();
        $this->assertSame(100.0, (float) $transfers->sole()->amount);
    }

    public function test_settlements_report_exposes_cash_differences(): void
    {
        $this->seedRun();

        $summary = $this->summary(RunSettlementsReport::class);
        $this->assertSame(1, $summary['Jornadas liquidadas']['value']);
        $this->assertSame(150.0, $summary['Efectivo esperado']['value']);
        $this->assertSame(140.0, $summary['Efectivo declarado']['value']);
        $this->assertSame(-10.0, $summary['Faltantes']['value']);
        $this->assertSame('Faltaron L 10.', $this->rows(RunSettlementsReport::class)->sole()['settlement_notes']);
    }

    public function test_receivables_are_aged_into_buckets_as_of_today(): void
    {
        $this->seedCredit();

        $summary = $this->summary(ReceivablesAgingReport::class);
        $this->assertSame(770.0, $summary['Saldo por cobrar']['value']);
        $this->assertSame(70.0, $summary['Vigente']['value']);
        $this->assertSame(200.0, $summary['8–15 días']['value']);
        $this->assertSame(500.0, $summary['Más de 60 días']['value']);
        $this->assertSame(0.0, $summary['1–7 días']['value']);

        $oldest = $this->rows(ReceivablesAgingReport::class)->first();
        $this->assertSame(90, $oldest['days_overdue']);
        $this->assertSame('Más de 60 días', $oldest['bucket']);
        $this->assertSame(100.0, (float) $oldest['paid_amount']);
    }

    public function test_credit_usage_and_exceptions_show_customers_over_their_limit(): void
    {
        $this->seedCredit();

        $summary = $this->summary(CreditUsageReport::class);
        $this->assertSame(300.0, $summary['Límite autorizado']['value']);
        $this->assertSame(700.0, $summary['Saldo utilizado']['value']);
        $this->assertSame(1, $summary['Sobre el límite']['value']);

        $usage = $this->rows(CreditUsageReport::class)->sole();
        $this->assertSame(-400.0, (float) $usage['available']);
        $this->assertSame(233.3, $usage['usage']);
        $this->assertSame(2, (int) $usage['overdue_documents']);
        $this->assertSame('Suspendido', $usage['status_label']);

        $exceptions = $this->summary(CreditExceptionsReport::class);
        $this->assertSame(1, $exceptions['Autorizaciones']['value']);
        $this->assertSame(200.0, $exceptions['Importe excedido']['value']);
    }

    public function test_balance_reports_hide_the_period_filter(): void
    {
        $this->seedCredit();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->get(route('reports.show', 'cartera-antiguedad'))
            ->assertOk()
            ->assertSee('Saldos al '.today()->format('d/m/Y'))
            ->assertDontSee('name="period"', false)
            ->assertSee('HNL 770.00');
        $this->actingAs($supervisor)->get(route('reports.show', 'liquidaciones'))
            ->assertOk()
            ->assertSee('Fecha de liquidación')
            ->assertSee('name="period"', false)
            ->assertDontSee('name="basis"', false);
    }

    /** @param array<string, string> $values */
    private function filters(array $values = []): ReportFilters
    {
        return new ReportFilters(
            period: 'custom',
            from: CarbonImmutable::today()->subDays(6),
            to: CarbonImmutable::today(),
            values: $values,
        );
    }

    /**
     * @param  class-string<Report>  $report
     * @return Collection<string, array<string, mixed>>
     */
    private function summary(string $report)
    {
        return collect(app($report)->summary($this->filters()))->keyBy('label');
    }

    /**
     * @param  class-string<Report>  $report
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(string $report)
    {
        $report = app($report);

        return $report->sortedQuery($this->filters())->get()->map(fn (object $row): array => $report->mapRow($row));
    }

    private function seedRun(): void
    {
        $run = DeliveryRun::factory()->create([
            'status' => DeliveryRunStatus::Settled,
            'driver_name' => 'Carlos Repartidor',
            'settled_at' => now(),
            'settled_by' => User::factory()->supervisor(),
            'delivered_total' => '320.0000',
            'collected_total' => '250.0000',
            'credit_total' => '70.0000',
            'cash_expected' => '150.0000',
            'cash_declared' => '140.0000',
            'cash_difference' => '-10.0000',
            'transfer_total' => '100.0000',
            'settlement_notes' => 'Faltaron L 10.',
        ]);
        DeliveryRun::factory()->create(['status' => DeliveryRunStatus::Draft]);
        $partial = $this->stop($run, 1, DeliveryOrderStatus::PartiallyDelivered, DeliveryOutcomeReason::CannotPay);
        $this->item($partial, 'Arroz precocido', '24', '22', ['returned_quantity' => '1', 'damaged_quantity' => '1']);
        $complete = $this->stop($run, 2, DeliveryOrderStatus::Delivered);
        $this->item($complete, 'Frijol rojo', '10', '10');
        $failed = $this->stop($run, 3, DeliveryOrderStatus::NotDelivered, DeliveryOutcomeReason::BusinessClosed);
        $this->item($failed, 'Azúcar', '6', '0', ['returned_quantity' => '6']);

        DeliveryPayment::factory()->create(['delivery_run_order_id' => $partial->id, 'amount' => '150.0000']);
        DeliveryPayment::factory()->create(['delivery_run_order_id' => $complete->id, 'amount' => '100.0000', 'method' => PaymentMethod::BankTransfer]);
        DeliveryPayment::factory()->create(['delivery_run_order_id' => $complete->id, 'amount' => '30.0000', 'status' => DeliveryPaymentStatus::Voided]);
        DeliveryPayment::factory()->create(['delivery_run_order_id' => $complete->id, 'amount' => '999.0000', 'received_at' => now()->subDays(30)]);

        $accountPayment = CustomerAccountEntry::factory()->create([
            'type' => CustomerAccountEntryType::CustomerPayment,
            'debit_amount' => '0.0000',
            'credit_amount' => '40.0000',
        ]);
        CustomerPaymentLine::factory()->create(['customer_account_entry_id' => $accountPayment->id, 'amount' => '40.0000']);
        CustomerAccountEntry::factory()->create([
            'type' => CustomerAccountEntryType::CustomerPayment,
            'status' => CustomerAccountEntryStatus::Reversed,
            'method' => PaymentMethod::Cash,
            'debit_amount' => '0.0000',
            'credit_amount' => '15.0000',
        ]);
    }

    private function seedCredit(): void
    {
        $customer = Customer::factory()->create(['business_name' => 'Mercadito Moroso']);
        CustomerCreditProfile::factory()->create([
            'customer_id' => $customer->id,
            'status' => CustomerCreditStatus::Suspended,
            'credit_limit' => '300.0000',
        ]);
        CustomerReceivable::factory()->create(['outstanding_amount' => '70.0000', 'original_amount' => '70.0000', 'due_date' => today()->addDays(5)]);
        CustomerReceivable::factory()->for($customer)->create(['outstanding_amount' => '200.0000', 'original_amount' => '200.0000', 'due_date' => today()->subDays(10)]);
        CustomerReceivable::factory()->for($customer)->create(['outstanding_amount' => '500.0000', 'original_amount' => '600.0000', 'due_date' => today()->subDays(90)]);
        CustomerReceivable::factory()->for($customer)->create(['outstanding_amount' => '0.0000', 'status' => 'paid', 'due_date' => today()->subDays(40)]);
        CustomerCreditAuthorization::factory()->create(['customer_id' => $customer->id, 'exceeded_by' => '200.0000']);
        CustomerCreditAuthorization::factory()->create(['authorized_at' => now()->subDays(40)]);
    }

    private function stop(DeliveryRun $run, int $visitOrder, DeliveryOrderStatus $status, ?DeliveryOutcomeReason $reason = null): DeliveryRunOrder
    {
        return DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => $visitOrder,
            'status' => $status,
            'outcome_status' => $status,
            'outcome_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, string> $outcome */
    private function item(DeliveryRunOrder $stop, string $name, string $loaded, string $delivered, array $outcome = []): void
    {
        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $stop->id,
            'product_name' => $name,
            'unit_price' => '10.0000',
            'requested_quantity' => $loaded,
            'prepared_quantity' => $loaded,
            'loaded_quantity' => $loaded,
            'delivered_quantity' => $delivered,
            'delivered_line_total' => bcmul($delivered, '10', 4),
            ...$outcome,
        ]);
    }
}
