<?php

namespace Tests\Feature\Api\V1;

use App\CustomerCreditStatus;
use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Device;
use App\Models\InventoryStock;
use App\Models\ProductPresentation;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\PaymentMethod;
use App\SettlementMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectedApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_each_role_gets_its_own_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->apiAbilities());
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure(['data' => ['management' => ['today' => ['sold', 'collected', 'credit'], 'receivables', 'pending' => [['key', 'label', 'count', 'critical']], 'runs', 'trend', 'unassigned_orders', 'overdue_receivables']]]);

        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        DeliveryRunOrder::factory()->create(['delivery_run_id' => $run->id, 'visit_order' => 1, 'status' => DeliveryOrderStatus::Loaded]);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.driver.current_run_id', $run->id)
            ->assertJsonPath('data.driver.metrics.stops', 1)
            ->assertJsonPath('data.driver.next_stop.visit_order', 1);

        $preventista = User::factory()->preventista()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure(['data' => ['panel' => ['cards', 'sections', 'trend']]]);
    }

    public function test_notifications_can_be_listed_and_marked_as_read(): void
    {
        $user = User::factory()->supervisor()->create();
        foreach (['Jornada lista', 'Faltante'] as $title) {
            $user->notify(new OperationalNotification(['type' => 'test', 'title' => $title, 'message' => '…', 'severity' => 'info', 'url' => '/dashboard']));
        }
        Sanctum::actingAs($user, $user->apiAbilities());

        $list = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 2)->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/notifications/'.$list->json('data.0.id').'/read')->assertJsonPath('unread_count', 1);
        $this->postJson('/api/v1/notifications/read-all')->assertJsonPath('unread_count', 0);
    }

    public function test_supervisor_reads_reports_as_data_pdf_and_csv(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());

        $this->getJson('/api/v1/reports')->assertOk()->assertJsonFragment(['key' => 'ventas-detalle']);
        $this->getJson('/api/v1/reports/ventas-detalle?period=week')
            ->assertOk()
            ->assertJsonPath('filters.period', 'week')
            ->assertJsonStructure(['report', 'filters' => ['options'], 'summary', 'charts', 'columns', 'rows', 'totals', 'meta']);
        $this->get('/api/v1/reports/ventas-detalle/download?period=week')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/reports/ventas-detalle/export?period=week')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->getJson('/api/v1/reports/no-existe')->assertNotFound();
        $this->getJson('/api/v1/route-reports')->assertOk()->assertJsonStructure(['data', 'meta']);

        $preventista = User::factory()->preventista()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $this->getJson('/api/v1/reports')->assertForbidden();
    }

    public function test_credit_is_managed_through_the_api_and_open_debts_sync_for_offline_payments(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());

        $this->putJson("/api/v1/customers/{$customer->id}/credit-profile", [
            'status' => CustomerCreditStatus::Active->value, 'credit_limit' => '5000', 'term_days' => 15, 'grace_days' => 3, 'reason' => 'Cliente nuevo con referencias.',
        ])->assertOk()->assertJsonPath('data.credit_limit', '5000.0000');
        $receivableId = $this->postJson("/api/v1/customers/{$customer->id}/opening-balances", [
            'amount' => '800', 'issued_at' => today()->subDays(20)->toDateString(), 'due_date' => today()->subDay()->toDateString(), 'notes' => 'Saldo anterior.',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/customers/{$customer->id}/credit")
            ->assertOk()
            ->assertJsonPath('data.open_receivables.0.id', $receivableId)
            ->assertJsonPath('data.open_receivables.0.is_overdue', true);
        $this->getJson('/api/v1/credits?overdue=1')->assertOk()->assertJsonPath('data.0.customer_id', $customer->id)->assertJsonPath('totals.overdue_balance', '800.0000');
        $this->getJson('/api/v1/sync/pull/receivables')->assertOk()->assertJsonPath('records.0.id', $receivableId);

        $paymentId = $this->postJson("/api/v1/customers/{$customer->id}/payments", [
            'amount' => '300',
            'payment_lines' => [['method' => PaymentMethod::Cash->value, 'amount' => '300']],
            'allocations' => [['receivable_id' => $receivableId, 'amount' => '300']],
        ])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/customers/{$customer->id}/payments/{$paymentId}/receipt")
            ->assertOk()
            ->assertJsonStructure(['data' => ['receipt', 'download_url', 'public_url', 'whatsapp_url']]);
        $this->get("/api/v1/customers/{$customer->id}/payments/{$paymentId}/receipt/download")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson("/api/v1/customers/{$customer->id}/payments/{$paymentId}/reversal", ['reason' => 'Billete falso.'])->assertCreated();

        $this->assertSame('800.0000', CustomerReceivable::query()->findOrFail($receivableId)->outstanding_amount);

        $preventista = User::factory()->preventista()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $this->getJson('/api/v1/sync/pull/receivables')->assertForbidden();
    }

    public function test_settled_delivery_invoice_details_and_pdf(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id, 'visit_order' => 1, 'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::Delivered, 'delivered_total' => '40.0000', 'balance_due' => '40.0000',
        ]);
        Sanctum::actingAs($driver, $driver->apiAbilities());

        $this->getJson("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/invoice")->assertNotFound();
        $this->postJson("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/payments", [
            'allocations' => [['method' => SettlementMethod::Cash->value, 'amount' => '40']],
        ])->assertCreated();

        $this->getJson("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.total', '40.0000')
            ->assertJsonStructure(['data' => ['invoice_number', 'payment_lines', 'download_urls' => ['letter', 'ticket'], 'public_url', 'whatsapp_url']]);
        $this->get("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/invoice/download?format=ticket")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_stock_replenishment_company_profile_and_devices(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = InventoryStock::factory()->create(['quantity_on_hand' => '10.000000', 'quantity_reserved' => '0.000000']);
        ProductPresentation::factory()->base()->create(['product_id' => $stock->product_id]);
        $device = Device::factory()->create();
        Sanctum::actingAs($admin, $admin->apiAbilities());

        $this->postJson("/api/v1/inventory/stocks/{$stock->id}/replenishments", ['quantity' => '5'])->assertCreated();
        $this->assertSame('15.000000', $stock->refresh()->quantity_on_hand);

        $this->getJson('/api/v1/company')->assertOk()->assertJsonStructure(['data' => ['commercial_name', 'logo_url']]);
        $this->postJson('/api/v1/company', ['commercial_name' => 'Distribuidora Norte', 'remove_logo' => false])
            ->assertOk()
            ->assertJsonPath('data.commercial_name', 'Distribuidora Norte');

        $this->getJson('/api/v1/devices')->assertOk()->assertJsonPath('data.0.code', $device->code);
        $this->postJson("/api/v1/devices/{$device->id}/revoke")->assertOk()->assertJsonPath('data.code', $device->code);
        $this->assertTrue($device->refresh()->isRevoked());
        $this->postJson("/api/v1/devices/{$device->id}/restore")->assertOk()->assertJsonPath('data.revoked_at', null);

        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());
        $this->getJson('/api/v1/company')->assertForbidden();
        $this->getJson('/api/v1/devices')->assertForbidden();
    }
}
