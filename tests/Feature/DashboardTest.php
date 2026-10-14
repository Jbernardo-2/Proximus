<?php

namespace Tests\Feature;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function roleDashboards(): array
    {
        return [
            'administrator' => [UserRole::Admin, 'Vendido hoy'],
            'supervisor' => [UserRole::Supervisor, 'Vendido hoy'],
            'salesperson' => [UserRole::Preventista, 'Tu cartera y tus pedidos en movimiento'],
            'warehouse' => [UserRole::Bodeguero, 'Lo que bodega debe sacar hoy'],
            'driver' => [UserRole::Repartidor, 'No tienes jornadas activas'],
        ];
    }

    #[DataProvider('roleDashboards')]
    public function test_renders_the_dashboard_designed_for_each_role(UserRole $role, string $heading): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($heading);
    }

    public function test_warehouse_dashboard_prioritizes_confirmed_unassigned_orders(): void
    {
        $warehouseUser = User::factory()->create();
        $order = Order::factory()->confirmed()->create([
            'customer_name' => 'Mercadito La Esperanza',
        ]);

        $this->actingAs($warehouseUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pedidos confirmados sin asignar')
            ->assertSee($order->order_number)
            ->assertSee('Mercadito La Esperanza');
    }

    public function test_management_dashboard_reports_todays_real_sales_collections_and_credit(): void
    {
        $run = DeliveryRun::factory()->create(['status' => DeliveryRunStatus::InTransit, 'driver_name' => 'Carlos Repartidor']);
        $deliveredToday = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => '100.0000',
            'balance_due' => '40.0000',
            'completed_at' => now(),
        ]);
        DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => 2,
            'status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => '900.0000',
            'balance_due' => '900.0000',
            'completed_at' => now()->subDay(),
        ]);
        DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => 3,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'delivered_total' => '70.0000',
            'balance_due' => '70.0000',
        ]);
        DeliveryPayment::factory()->create(['delivery_run_order_id' => $deliveredToday->id, 'amount' => '60.0000']);
        DeliveryPayment::factory()->create([
            'delivery_run_order_id' => $deliveredToday->id,
            'amount' => '25.0000',
            'status' => DeliveryPaymentStatus::Voided,
        ]);
        DeliveryPayment::factory()->create([
            'delivery_run_order_id' => $deliveredToday->id,
            'amount' => '500.0000',
            'received_at' => now()->subDay(),
        ]);
        CustomerAccountEntry::factory()->create([
            'type' => CustomerAccountEntryType::CustomerPayment,
            'debit_amount' => '0.0000',
            'credit_amount' => '30.0000',
        ]);
        CustomerAccountEntry::factory()->create([
            'type' => CustomerAccountEntryType::CustomerPayment,
            'status' => CustomerAccountEntryStatus::Reversed,
            'debit_amount' => '0.0000',
            'credit_amount' => '15.0000',
        ]);
        CustomerReceivable::factory()->create(['outstanding_amount' => '200.0000']);
        CustomerReceivable::factory()->create(['outstanding_amount' => '80.0000', 'due_date' => today()->subDays(3)]);

        $dashboard = app(DashboardService::class)->management();

        $this->assertSame('100.0000', bcadd($dashboard['today']['sold'], '0', 4));
        $this->assertSame(1, $dashboard['today']['deliveries']);
        $this->assertSame('90.0000', $dashboard['today']['collected']);
        $this->assertSame('60.0000', bcadd($dashboard['today']['collected_on_delivery'], '0', 4));
        $this->assertSame('30.0000', bcadd($dashboard['today']['collected_on_account'], '0', 4));
        $this->assertSame('40.0000', bcadd($dashboard['today']['credit'], '0', 4));
        $this->assertSame('280.0000', bcadd($dashboard['receivables']['outstanding'], '0', 4));
        $this->assertSame('80.0000', bcadd($dashboard['receivables']['overdue'], '0', 4));
        $this->assertSame(1, $dashboard['receivables']['overdue_customers']);
        $this->assertSame([900.0, 100.0], array_slice($dashboard['trend']['sold'], -2));
        $this->assertSame([500.0, 90.0], array_slice($dashboard['trend']['collected'], -2));

        $this->actingAs(User::factory()->supervisor()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HNL 100.00')
            ->assertSee('HNL 90.00')
            ->assertSee('Vencido HNL 80.00 · 1 cliente')
            ->assertSee('Carlos Repartidor')
            ->assertSee('2 de 3 paradas')
            ->assertSee('3 días de atraso');
    }
}
