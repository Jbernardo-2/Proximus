<?php

namespace Tests\Unit\Authorization;

use App\CustomerAccountEntryType;
use App\Models\CustomerAccountEntry;
use App\Models\User;
use App\Policies\CustomerAccountEntryPolicy;
use App\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerAccountEntryAccessTest extends TestCase
{
    public static function roles(): array
    {
        return [
            'administrator' => [UserRole::Admin, true],
            'supervisor' => [UserRole::Supervisor, true],
            'salesperson' => [UserRole::Preventista, true],
            'warehouse' => [UserRole::Bodeguero, false],
            'driver' => [UserRole::Repartidor, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_only_credit_roles_can_view_customer_payment_receipts(UserRole $role, bool $expected): void
    {
        $user = new User(['role' => $role, 'is_active' => true]);
        $payment = new CustomerAccountEntry(['type' => CustomerAccountEntryType::CustomerPayment]);

        $this->assertSame($expected, (new CustomerAccountEntryPolicy)->view($user, $payment));
    }

    public function test_non_payment_entries_and_inactive_users_cannot_be_viewed_as_receipts(): void
    {
        $policy = new CustomerAccountEntryPolicy;
        $activeSupervisor = new User(['role' => UserRole::Supervisor, 'is_active' => true]);
        $inactiveSupervisor = new User(['role' => UserRole::Supervisor, 'is_active' => false]);
        $sale = new CustomerAccountEntry(['type' => CustomerAccountEntryType::Sale]);
        $payment = new CustomerAccountEntry(['type' => CustomerAccountEntryType::CustomerPayment]);

        $this->assertFalse($policy->view($activeSupervisor, $sale));
        $this->assertFalse($policy->view($inactiveSupervisor, $payment));
    }
}
