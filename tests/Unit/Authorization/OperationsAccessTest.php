<?php

namespace Tests\Unit\Authorization;

use App\Models\User;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsAccessTest extends TestCase
{
    public static function companyProfileRoles(): array
    {
        return [
            'admin' => [UserRole::Admin, true],
            'supervisor' => [UserRole::Supervisor, false],
            'preventista' => [UserRole::Preventista, false],
            'bodeguero' => [UserRole::Bodeguero, false],
            'repartidor' => [UserRole::Repartidor, false],
        ];
    }

    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, ['catalog:manage', 'customers:manage', 'credits:view', 'credits:configure', 'payments:record', 'payments:reverse', 'routes:view', 'routes:manage', 'orders:view', 'orders:manage', 'orders:override', 'orders:lifecycle', 'inventory:view', 'inventory:operate', 'inventory:adjust', 'inventory:configure', 'deliveries:view', 'deliveries:manage', 'deliveries:prepare', 'deliveries:execute', 'deliveries:settle', 'vehicles:manage', 'users:manage', 'reports:view', 'route-reports:view', 'company:manage']],
            'supervisor' => [UserRole::Supervisor, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, ['catalog:manage', 'customers:manage', 'credits:view', 'credits:configure', 'payments:record', 'payments:reverse', 'routes:view', 'routes:manage', 'orders:view', 'orders:manage', 'orders:override', 'orders:lifecycle', 'inventory:view', 'inventory:operate', 'inventory:adjust', 'inventory:configure', 'deliveries:view', 'deliveries:manage', 'deliveries:prepare', 'deliveries:execute', 'deliveries:settle', 'vehicles:manage', 'reports:view', 'route-reports:view']],
            'preventista' => [UserRole::Preventista, true, true, true, false, true, false, false, true, false, true, true, false, false, true, false, false, false, ['customers:manage', 'credits:view', 'payments:record', 'routes:view', 'orders:view', 'orders:manage', 'inventory:view', 'route-reports:view']],
            'bodeguero' => [UserRole::Bodeguero, true, false, false, false, false, false, false, false, false, true, false, false, false, true, true, false, false, ['catalog:manage', 'orders:view', 'inventory:view', 'inventory:operate', 'deliveries:view', 'deliveries:manage', 'deliveries:prepare', 'route-reports:view']],
            'repartidor' => [UserRole::Repartidor, true, false, false, false, true, false, false, false, false, false, false, false, false, false, false, false, false, ['payments:record', 'deliveries:view', 'deliveries:execute', 'route-reports:view']],
        ];
    }

    /** @param list<string> $abilities */
    #[DataProvider('roles')]
    public function test_role_has_expected_operations_access(
        UserRole $role,
        bool $canAccessPanel,
        bool $canManageCustomers,
        bool $canViewCustomerCredit,
        bool $canConfigureCustomerCredit,
        bool $canRecordCustomerPayments,
        bool $canReverseCustomerPayments,
        bool $canOverrideCustomerCredit,
        bool $canViewRoutes,
        bool $canManageRoutes,
        bool $canViewOrders,
        bool $canManageOrders,
        bool $canOverrideOrderPrices,
        bool $canManageOrderLifecycle,
        bool $canViewInventory,
        bool $canOperateInventory,
        bool $canAdjustInventory,
        bool $canConfigureInventory,
        array $abilities,
    ): void {
        $user = User::factory()->make([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->assertSame($canAccessPanel, $user->canAccessPanel());
        $this->assertSame($canManageCustomers, $user->canManageCustomers());
        $this->assertSame($canViewCustomerCredit, $user->canViewCustomerCredit());
        $this->assertSame($canConfigureCustomerCredit, $user->canConfigureCustomerCredit());
        $this->assertSame($canRecordCustomerPayments, $user->canRecordCustomerPayments());
        $this->assertSame($canReverseCustomerPayments, $user->canReverseCustomerPayments());
        $this->assertSame($canOverrideCustomerCredit, $user->canOverrideCustomerCredit());
        $this->assertSame($canViewRoutes, $user->canViewRoutes());
        $this->assertSame($canManageRoutes, $user->canManageRoutes());
        $this->assertSame($canViewOrders, $user->canViewOrders());
        $this->assertSame($canManageOrders, $user->canManageOrders());
        $this->assertSame($canOverrideOrderPrices, $user->canOverrideOrderPrices());
        $this->assertSame($canManageOrderLifecycle, $user->canManageOrderLifecycle());
        $this->assertSame($canViewInventory, $user->canViewInventory());
        $this->assertSame($canOperateInventory, $user->canOperateInventory());
        $this->assertSame($canAdjustInventory, $user->canAdjustInventory());
        $this->assertSame($canConfigureInventory, $user->canConfigureInventory());
        $this->assertSame($abilities, $user->apiAbilities());
    }

    public function test_inactive_user_cannot_access_operations(): void
    {
        $user = User::factory()->admin()->make(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel());
        $this->assertFalse($user->canManageCustomers());
        $this->assertFalse($user->canViewCustomerCredit());
        $this->assertFalse($user->canConfigureCustomerCredit());
        $this->assertFalse($user->canRecordCustomerPayments());
        $this->assertFalse($user->canReverseCustomerPayments());
        $this->assertFalse($user->canOverrideCustomerCredit());
        $this->assertFalse($user->canViewRoutes());
        $this->assertFalse($user->canManageRoutes());
        $this->assertFalse($user->canViewOrders());
        $this->assertFalse($user->canManageOrders());
        $this->assertFalse($user->canOverrideOrderPrices());
        $this->assertFalse($user->canManageOrderLifecycle());
        $this->assertFalse($user->canViewInventory());
        $this->assertFalse($user->canOperateInventory());
        $this->assertFalse($user->canAdjustInventory());
        $this->assertFalse($user->canConfigureInventory());
        $this->assertFalse($user->canViewRouteReports());
        $this->assertFalse($user->canManageCompanyProfile());
        $this->assertSame([], $user->apiAbilities());
    }

    public function test_every_active_operational_role_can_view_route_reports(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->make([
                'role' => $role,
                'is_active' => true,
            ]);

            $this->assertTrue($user->canViewRouteReports(), $role->value);
        }
    }

    #[DataProvider('companyProfileRoles')]
    public function test_only_administrator_can_manage_company_profile(UserRole $role, bool $expected): void
    {
        $user = User::factory()->make([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->assertSame($expected, $user->canManageCompanyProfile());
    }
}
