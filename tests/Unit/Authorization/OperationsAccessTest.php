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
            'admin' => [UserRole::Admin, true, true, true, ['catalog:manage', 'customers:manage', 'routes:manage', 'users:manage']],
            'supervisor' => [UserRole::Supervisor, true, true, true, ['catalog:manage', 'customers:manage', 'routes:manage']],
            'preventista' => [UserRole::Preventista, true, true, true, ['customers:manage', 'routes:manage']],
            'bodeguero' => [UserRole::Bodeguero, true, false, false, ['catalog:manage']],
            'repartidor' => [UserRole::Repartidor, false, false, false, []],
        ];
    }

    /** @param list<string> $abilities */
    #[DataProvider('roles')]
    public function test_role_has_expected_operations_access(
        UserRole $role,
        bool $canAccessPanel,
        bool $canManageCustomers,
        bool $canManageRoutes,
        array $abilities,
    ): void {
        $user = User::factory()->make([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->assertSame($canAccessPanel, $user->canAccessPanel());
        $this->assertSame($canManageCustomers, $user->canManageCustomers());
        $this->assertSame($canManageRoutes, $user->canManageRoutes());
        $this->assertSame($abilities, $user->apiAbilities());
    }

    public function test_inactive_user_cannot_access_operations(): void
    {
        $user = User::factory()->admin()->make(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel());
        $this->assertFalse($user->canManageCustomers());
        $this->assertFalse($user->canManageRoutes());
        $this->assertSame([], $user->apiAbilities());
    }
}
