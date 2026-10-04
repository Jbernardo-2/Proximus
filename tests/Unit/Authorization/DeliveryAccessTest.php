<?php

namespace Tests\Unit\Authorization;

use App\Models\User;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryAccessTest extends TestCase
{
    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, true, true, true, true, true, true, ['deliveries:view', 'deliveries:manage', 'deliveries:prepare', 'deliveries:execute', 'deliveries:settle', 'vehicles:manage']],
            'supervisor' => [UserRole::Supervisor, true, true, true, true, true, true, ['deliveries:view', 'deliveries:manage', 'deliveries:prepare', 'deliveries:execute', 'deliveries:settle', 'vehicles:manage']],
            'preventista' => [UserRole::Preventista, false, false, false, false, false, false, []],
            'bodeguero' => [UserRole::Bodeguero, true, false, true, false, false, false, ['deliveries:view', 'deliveries:prepare']],
            'repartidor' => [UserRole::Repartidor, true, false, false, true, false, false, ['deliveries:view', 'deliveries:execute']],
        ];
    }

    /** @param list<string> $abilities */
    #[DataProvider('roles')]
    public function test_role_has_expected_delivery_access(
        UserRole $role,
        bool $canView,
        bool $canManage,
        bool $canPrepare,
        bool $canExecute,
        bool $canSettle,
        bool $canManageVehicles,
        array $abilities,
    ): void {
        $user = User::factory()->make(['role' => $role, 'is_active' => true]);

        $this->assertSame($canView, $user->canViewDeliveries());
        $this->assertSame($canManage, $user->canManageDeliveries());
        $this->assertSame($canPrepare, $user->canPrepareDeliveries());
        $this->assertSame($canExecute, $user->canExecuteDeliveries());
        $this->assertSame($canSettle, $user->canSettleDeliveries());
        $this->assertSame($canManageVehicles, $user->canManageVehicles());
        $this->assertSame(
            $abilities,
            array_values(array_filter(
                $user->apiAbilities(),
                fn (string $ability): bool => str_starts_with($ability, 'deliveries:') || $ability === 'vehicles:manage',
            )),
        );
    }

    public function test_inactive_user_has_no_delivery_access(): void
    {
        $user = User::factory()->admin()->make(['is_active' => false]);

        $this->assertFalse($user->canViewDeliveries());
        $this->assertFalse($user->canManageDeliveries());
        $this->assertFalse($user->canPrepareDeliveries());
        $this->assertFalse($user->canExecuteDeliveries());
        $this->assertFalse($user->canSettleDeliveries());
        $this->assertFalse($user->canManageVehicles());
    }
}
