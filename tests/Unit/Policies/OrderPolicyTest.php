<?php

namespace Tests\Unit\Policies;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\Policies\OrderPolicy;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    public static function viewCases(): array
    {
        return [
            'admin sees every draft' => [UserRole::Admin, 10, 20, OrderStatus::Draft, true],
            'supervisor sees every draft' => [UserRole::Supervisor, 10, 20, OrderStatus::Draft, true],
            'preventista sees own draft' => [UserRole::Preventista, 10, 10, OrderStatus::Draft, true],
            'preventista cannot see another draft' => [UserRole::Preventista, 10, 20, OrderStatus::Draft, false],
            'bodeguero sees confirmed order' => [UserRole::Bodeguero, 10, 20, OrderStatus::Confirmed, true],
            'bodeguero cannot see draft' => [UserRole::Bodeguero, 10, 20, OrderStatus::Draft, false],
            'repartidor sees no order' => [UserRole::Repartidor, 10, 10, OrderStatus::Confirmed, false],
        ];
    }

    #[DataProvider('viewCases')]
    public function test_view_permission_matches_role_scope(
        UserRole $role,
        int $userId,
        int $salespersonId,
        OrderStatus $status,
        bool $expected,
    ): void {
        $user = User::factory()->make(['id' => $userId, 'role' => $role]);
        $order = new Order(['status' => $status]);
        $order->salesperson_id = $salespersonId;

        $this->assertSame($expected, (new OrderPolicy)->view($user, $order));
    }

    public function test_only_supervision_can_override_prices_and_manage_lifecycle(): void
    {
        $admin = User::factory()->admin()->make();
        $supervisor = User::factory()->supervisor()->make();
        $preventista = User::factory()->preventista()->make();
        $draft = new Order(['status' => OrderStatus::Draft]);
        $confirmed = new Order(['status' => OrderStatus::Confirmed]);
        $policy = new OrderPolicy;

        $this->assertTrue($policy->overridePrice($admin, $draft));
        $this->assertTrue($policy->overridePrice($supervisor, $draft));
        $this->assertFalse($policy->overridePrice($preventista, $draft));
        $this->assertFalse($policy->overridePrice($admin, $confirmed));
        $this->assertTrue($policy->cancel($supervisor, $confirmed));
        $this->assertTrue($policy->reopen($admin, $confirmed));
        $this->assertFalse($policy->cancel($preventista, $confirmed));
        $this->assertFalse($policy->reopen($preventista, $confirmed));
    }
}
