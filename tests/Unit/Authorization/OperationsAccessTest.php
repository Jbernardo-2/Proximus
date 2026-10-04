<?php

namespace Tests\Unit\Authorization;

use App\Models\User;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsAccessTest extends TestCase
{
    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, true, true, true, true, true, true, true, true, ['catalog:manage', 'customers:manage', 'routes:view', 'routes:manage', 'orders:view', 'orders:manage', 'orders:override', 'orders:lifecycle', 'users:manage']],
            'supervisor' => [UserRole::Supervisor, true, true, true, true, true, true, true, true, ['catalog:manage', 'customers:manage', 'routes:view', 'routes:manage', 'orders:view', 'orders:manage', 'orders:override', 'orders:lifecycle']],
            'preventista' => [UserRole::Preventista, true, true, true, false, true, true, false, false, ['customers:manage', 'routes:view', 'orders:view', 'orders:manage']],
            'bodeguero' => [UserRole::Bodeguero, true, false, false, false, true, false, false, false, ['catalog:manage', 'orders:view']],
            'repartidor' => [UserRole::Repartidor, false, false, false, false, false, false, false, false, []],
        ];
    }

    /** @param list<string> $abilities */
    #[DataProvider('roles')]
    public function test_role_has_expected_operations_access(
        UserRole $role,
        bool $canAccessPanel,
        bool $canManageCustomers,
        bool $canViewRoutes,
        bool $canManageRoutes,
        bool $canViewOrders,
        bool $canManageOrders,
        bool $canOverrideOrderPrices,
        bool $canManageOrderLifecycle,
        array $abilities,
    ): void {
        $user = User::factory()->make([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->assertSame($canAccessPanel, $user->canAccessPanel());
        $this->assertSame($canManageCustomers, $user->canManageCustomers());
        $this->assertSame($canViewRoutes, $user->canViewRoutes());
        $this->assertSame($canManageRoutes, $user->canManageRoutes());
        $this->assertSame($canViewOrders, $user->canViewOrders());
        $this->assertSame($canManageOrders, $user->canManageOrders());
        $this->assertSame($canOverrideOrderPrices, $user->canOverrideOrderPrices());
        $this->assertSame($canManageOrderLifecycle, $user->canManageOrderLifecycle());
        $this->assertSame($abilities, $user->apiAbilities());
    }

    public function test_inactive_user_cannot_access_operations(): void
    {
        $user = User::factory()->admin()->make(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel());
        $this->assertFalse($user->canManageCustomers());
        $this->assertFalse($user->canViewRoutes());
        $this->assertFalse($user->canManageRoutes());
        $this->assertFalse($user->canViewOrders());
        $this->assertFalse($user->canManageOrders());
        $this->assertFalse($user->canOverrideOrderPrices());
        $this->assertFalse($user->canManageOrderLifecycle());
        $this->assertSame([], $user->apiAbilities());
    }
}
