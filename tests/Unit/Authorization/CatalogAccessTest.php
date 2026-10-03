<?php

namespace Tests\Unit\Authorization;

use App\Models\User;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAccessTest extends TestCase
{
    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, true],
            'supervisor' => [UserRole::Supervisor, true],
            'bodeguero' => [UserRole::Bodeguero, true],
            'preventista' => [UserRole::Preventista, false],
            'repartidor' => [UserRole::Repartidor, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_role_has_expected_catalog_access(UserRole $role, bool $expected): void
    {
        $user = User::factory()->make([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->assertSame($expected, $user->canManageCatalog());
    }

    public function test_inactive_user_cannot_manage_catalog(): void
    {
        $user = User::factory()->make([
            'role' => UserRole::Admin,
            'is_active' => false,
        ]);

        $this->assertFalse($user->canManageCatalog());
    }
}
