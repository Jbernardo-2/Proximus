<?php

namespace Tests\Feature\Api\V1;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\DeliverySettlement;
use App\Models\Device;
use App\Models\SyncOperation;
use App\Models\User;
use App\PaymentMethod;
use App\SettlementMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_phone_is_registered_on_login_and_keeps_its_code_across_logins(): void
    {
        $driver = User::factory()->repartidor()->create();

        $first = $this->login($driver, 'instalacion-a1');
        $first->assertCreated()
            ->assertJsonPath('device.code', 'M001')
            ->assertJsonPath('device.series.invoice', ['prefix' => 'FAC-M001-', 'last_number' => 0]);
        $this->login(User::factory()->repartidor()->create(), 'instalacion-b2')->assertJsonPath('device.code', 'M002');

        $again = $this->login($driver, 'instalacion-a1')->assertJsonPath('device.code', 'M001');

        $this->assertSame(2, Device::query()->count());
        $this->assertSame(2, PersonalAccessToken::query()->count());
        $this->assertNotSame($first->json('token'), $again->json('token'));
        $this->getJson('/api/v1/sync', $this->bearer($first->json('token')))->assertUnauthorized();
        $this->getJson('/api/v1/sync', $this->bearer($again->json('token')))
            ->assertOk()
            ->assertJsonPath('device.code', 'M001');
    }

    public function test_a_blocked_phone_is_signed_out_and_cannot_sign_in_until_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $driver = User::factory()->repartidor()->create();
        $token = $this->login($driver, 'telefono-perdido')->json('token');
        $device = Device::query()->sole();

        $this->actingAs($admin)->get(route('devices.index'))->assertOk()->assertSee('M001');
        $this->post(route('devices.revoke', $device))->assertRedirect();
        $this->app['auth']->forgetGuards();

        $this->assertNotNull($device->refresh()->revoked_at);
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->getJson('/api/v1/sync', $this->bearer($token))->assertUnauthorized();
        $this->login($driver, 'telefono-perdido')
            ->assertStatus(423)
            ->assertJsonPath('code', 'device_revoked');

        $this->actingAs($admin)->post(route('devices.restore', $device))->assertRedirect();
        $this->app['auth']->forgetGuards();

        $this->login($driver, 'telefono-perdido')->assertCreated()->assertJsonPath('device.code', 'M001');
    }

    public function test_a_token_in_use_is_renewed_for_the_same_phone_with_current_abilities(): void
    {
        $user = User::factory()->preventista()->create();
        $first = $this->login($user, 'telefono-renovacion');
        $device = Device::query()->sole();
        $this->travel(10)->days();

        $renewed = $this->postJson('/api/v1/tokens/refresh', [], $this->bearer($first->json('token')))
            ->assertOk()
            ->assertJsonPath('device.code', $device->code)
            ->assertJsonPath('abilities', $user->apiAbilities());

        $this->assertTrue(now()->addDays(29)->lessThan($renewed->json('expires_at')), 'the expiry starts again');
        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertSame(PersonalAccessToken::query()->value('id'), $device->refresh()->personal_access_token_id);
        $this->getJson('/api/v1/sync', $this->bearer($first->json('token')))->assertUnauthorized();
        $this->getJson('/api/v1/sync', $this->bearer($renewed->json('token')))->assertOk();

        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/v1/tokens/refresh', [], $this->bearer($renewed->json('token')))->assertForbidden();
    }

    public function test_only_administrators_manage_phones(): void
    {
        $device = Device::factory()->create();

        $this->actingAs(User::factory()->supervisor()->create())->get(route('devices.index'))->assertForbidden();
        $this->post(route('devices.revoke', $device))->assertForbidden();
    }

    public function test_numbers_printed_offline_are_kept_and_the_series_resumes_from_the_server(): void
    {
        $driver = User::factory()->repartidor()->create();
        [$run, $runOrder] = $this->awaitingPayment($driver, '30.0000');
        [, $otherRunOrder] = $this->awaitingPayment($driver, '20.0000', $run);
        $token = $this->login($driver, 'telefono-ruta-1')->json('token');
        $ids = ['delivery_run_id' => $run->id];

        $response = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'tel:pago-1', 'type' => 'delivery.payment', 'payload' => [...$ids,
                'run_order_id' => $runOrder->id,
                'invoice_number' => 'FAC-M001-000001',
                'allocations' => [
                    ['method' => SettlementMethod::Cash->value, 'amount' => '10', 'receipt_number' => 'REC-M001-000001'],
                    ['method' => SettlementMethod::BankTransfer->value, 'amount' => '20', 'receipt_number' => 'REC-M001-000002'],
                ],
            ]],
            ['client_reference' => 'tel:pago-2', 'type' => 'delivery.payment', 'payload' => [...$ids,
                'run_order_id' => $otherRunOrder->id,
                'invoice_number' => 'FAC-M999-000001',
                'allocations' => [['method' => SettlementMethod::Cash->value, 'amount' => '20']],
            ]],
        ]], $this->bearer($token))->assertOk();

        $this->assertSame(['accepted', 'accepted'], array_column($response->json('results'), 'status'));
        $this->assertSame('FAC-M001-000001', $runOrder->settlement()->value('invoice_number'));
        $this->assertEqualsCanonicalizing(['REC-M001-000001', 'REC-M001-000002'], $runOrder->payments()->pluck('receipt_number')->all());
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{6}$/', (string) $otherRunOrder->settlement()->value('invoice_number'));
        $this->assertSame('M001', SyncOperation::query()->first()->device->code);

        $this->getJson('/api/v1/sync', $this->bearer($token))
            ->assertJsonPath('device.series.invoice.last_number', 1)
            ->assertJsonPath('device.series.receipt.last_number', 2)
            ->assertJsonPath('device.series.customer_payment.last_number', 0);
    }

    public function test_device_numbers_are_ignored_online_and_when_already_used(): void
    {
        $driver = User::factory()->repartidor()->create();
        [$run, $runOrder] = $this->awaitingPayment($driver, '15.0000');
        [, $otherRunOrder] = $this->awaitingPayment($driver, '15.0000', $run);
        $token = $this->login($driver, 'telefono-ruta-2')->json('token');
        $payment = ['invoice_number' => 'FAC-M001-000001', 'allocations' => [['method' => SettlementMethod::Cash->value, 'amount' => '15']]];

        $this->postJson("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/payments", $payment, $this->bearer($token))->assertCreated();
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{6}$/', (string) $runOrder->settlement()->value('invoice_number'));

        DeliverySettlement::query()->whereKey($runOrder->settlement()->value('id'))->update(['invoice_number' => 'FAC-M001-000001']);
        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'tel:repetido', 'type' => 'delivery.payment', 'payload' => [...$payment, 'delivery_run_id' => $run->id, 'run_order_id' => $otherRunOrder->id]],
        ]], $this->bearer($token))->assertJsonPath('results.0.status', 'accepted');

        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{6}$/', (string) $otherRunOrder->settlement()->value('invoice_number'));
        $this->assertSame(2, DeliveryPayment::query()->where('receipt_number', 'like', 'REC-2%')->count());
    }

    public function test_an_account_payment_taken_offline_keeps_the_phone_receipt_number(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $receivable = CustomerReceivable::factory()->create(['outstanding_amount' => '100.0000']);
        $token = $this->login($supervisor, 'telefono-cobro')->json('token');

        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'tel:abono-1', 'type' => 'customer.payment', 'payload' => [
                'customer_id' => $receivable->customer_id,
                'receipt_number' => 'ABO-M001-000007',
                'amount' => '40',
                'payment_lines' => [['method' => PaymentMethod::Cash->value, 'amount' => '40']],
                'allocations' => [['receivable_id' => $receivable->id, 'amount' => '40']],
            ]],
        ]], $this->bearer($token))->assertJsonPath('results.0.status', 'accepted');

        $this->assertTrue(CustomerAccountEntry::query()->where('receipt_number', 'ABO-M001-000007')->exists());
        $this->getJson('/api/v1/sync', $this->bearer($token))->assertJsonPath('device.series.customer_payment.last_number', 7);
    }

    private function login(User $user, string $installationId): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Samsung A15',
            'installation_id' => $installationId,
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    /** @return array{DeliveryRun, DeliveryRunOrder} */
    private function awaitingPayment(User $driver, string $total, ?DeliveryRun $run = null): array
    {
        $run ??= DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => $run->runOrders()->count() + 1,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => $total,
            'balance_due' => $total,
        ]);

        return [$run, $runOrder];
    }
}
