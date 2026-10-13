<?php

namespace Tests\Feature;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerCreditAuthorizationType;
use App\CustomerCreditStatus;
use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerReceivable;
use App\Models\Order;
use App\Models\User;
use App\PaymentMethod;
use App\Services\CustomerCreditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supervisor_configures_credit_policy_and_change_is_audited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor)->put(route('customers.credit-profile.update', $customer), [
            'status' => CustomerCreditStatus::Active->value,
            'credit_limit' => '5000',
            'term_days' => 30,
            'grace_days' => 5,
            'notes' => 'Crédito aprobado contra entrega semanal.',
            'reason' => 'Aprobado por supervisión después de revisar su historial.',
        ])->assertRedirect(route('customers.show', $customer).'#credit');

        $this->assertDatabaseHas('customer_credit_profiles', [
            'customer_id' => $customer->id,
            'status' => CustomerCreditStatus::Active->value,
            'credit_limit' => 5000,
            'term_days' => 30,
            'grace_days' => 5,
            'approved_by' => $supervisor->id,
            'updated_by' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('customer_credit_profile_histories', [
            'customer_id' => $customer->id,
            'from_status' => null,
            'to_status' => CustomerCreditStatus::Active->value,
            'changed_by' => $supervisor->id,
        ]);
    }

    public function test_preventista_can_view_but_cannot_configure_customer_credit(): void
    {
        $preventista = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        CustomerCreditProfile::factory()->for($customer)->create();

        $this->actingAs($preventista)
            ->get(route('credits.index'))
            ->assertOk()
            ->assertSee($customer->business_name);

        $this->actingAs($preventista)->put(route('customers.credit-profile.update', $customer), [
            'status' => CustomerCreditStatus::Active->value,
            'credit_limit' => '9000',
            'term_days' => 30,
            'grace_days' => 0,
            'reason' => 'Intento no autorizado.',
        ])->assertForbidden();
    }

    public function test_opening_balance_creates_receivable_and_account_debit_without_an_order(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor)->post(route('customers.opening-balances.store', $customer), [
            'amount' => '2300',
            'document_number' => 'SALDO-ANTERIOR-001',
            'issued_at' => today()->subMonth()->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'notes' => 'Saldo importado del cuaderno anterior.',
        ])->assertRedirect(route('customers.show', $customer).'#account');

        $receivable = CustomerReceivable::query()->sole();
        $this->assertNull($receivable->order_id);
        $this->assertSame('2300.0000', $receivable->outstanding_amount);
        $this->assertTrue($receivable->isOverdue());
        $this->assertDatabaseHas('customer_account_entries', [
            'customer_id' => $customer->id,
            'type' => CustomerAccountEntryType::OpeningBalance->value,
            'receipt_number' => 'SALDO-ANTERIOR-001',
            'debit_amount' => 2300,
            'credit_amount' => 0,
        ]);
    }

    public function test_combined_payment_preserves_each_method_and_leaves_partial_credit_balance(): void
    {
        [$supervisor, $customer, $receivable] = $this->customerWithOpeningDebt('2300');

        $response = $this->actingAs($supervisor)->post(route('customers.payments.store', $customer), [
            'amount' => '1500',
            'payment_lines' => [
                ['method' => PaymentMethod::Cash->value, 'amount' => '500', 'reference' => null],
                ['method' => PaymentMethod::BankTransfer->value, 'amount' => '1000', 'reference' => 'TRX-1000'],
            ],
            'allocations' => [
                ['receivable_id' => $receivable->id, 'amount' => '1500'],
            ],
            'notes' => 'Pago combinado acordado en visita.',
        ]);

        $payment = CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment->value)
            ->sole();
        $response->assertRedirect(route('customers.payments.receipt.show', [$customer, $payment]));
        $this->assertNull($payment->method);
        $this->assertSame('1500.0000', $payment->credit_amount);
        $this->assertCount(2, $payment->paymentLines);
        $this->assertDatabaseHas('customer_payment_lines', [
            'customer_account_entry_id' => $payment->id,
            'method' => PaymentMethod::Cash->value,
            'amount' => 500,
        ]);
        $this->assertDatabaseHas('customer_payment_lines', [
            'customer_account_entry_id' => $payment->id,
            'method' => PaymentMethod::BankTransfer->value,
            'amount' => 1000,
            'reference' => 'TRX-1000',
        ]);
        $this->assertSame('800.0000', $receivable->refresh()->outstanding_amount);
        $this->assertSame(CustomerReceivableStatus::PartiallyPaid, $receivable->status);
    }

    public function test_reversing_customer_payment_restores_debt_and_keeps_an_audit_entry(): void
    {
        [$supervisor, $customer, $receivable] = $this->customerWithOpeningDebt('1000');
        $this->actingAs($supervisor)->post(route('customers.payments.store', $customer), [
            'amount' => '400',
            'payment_lines' => [
                ['method' => PaymentMethod::Cash->value, 'amount' => '400', 'reference' => null],
            ],
            'allocations' => [
                ['receivable_id' => $receivable->id, 'amount' => '400'],
            ],
        ]);
        $payment = CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment->value)
            ->sole();

        $this->actingAs($supervisor)->post(route('customers.payments.reverse', [$customer, $payment]), [
            'reason' => 'El efectivo fue contado incorrectamente.',
        ])->assertRedirect(route('customers.show', $customer).'#account');

        $this->assertSame(CustomerAccountEntryStatus::Reversed, $payment->refresh()->status);
        $this->assertSame('1000.0000', $receivable->refresh()->outstanding_amount);
        $this->assertSame(CustomerReceivableStatus::Open, $receivable->status);
        $this->assertDatabaseHas('customer_account_entries', [
            'customer_id' => $customer->id,
            'type' => CustomerAccountEntryType::Reversal->value,
            'reversal_of_id' => $payment->id,
            'debit_amount' => 400,
            'notes' => 'El efectivo fue contado incorrectamente.',
        ]);

        $this->actingAs($supervisor)->post(route('customers.payments.reverse', [$customer, $payment]), [
            'reason' => 'Segundo intento.',
        ])->assertSessionHasErrors('payment');
    }

    public function test_credit_limit_requires_an_authorized_and_explained_override(): void
    {
        $preventista = User::factory()->preventista()->create();
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        CustomerCreditProfile::factory()->for($customer)->create(['credit_limit' => '1000']);
        CustomerReceivable::factory()->for($customer)->create([
            'order_id' => null,
            'delivery_run_order_id' => null,
            'original_amount' => '800',
            'outstanding_amount' => '800',
        ]);
        $order = Order::factory()->for($customer)->create(['total' => '300']);
        $credit = app(CustomerCreditService::class);

        try {
            $credit->authorize(
                customer: $customer,
                amount: '300',
                actor: $preventista,
                type: CustomerCreditAuthorizationType::OrderConfirmation,
                order: $order,
            );
            $this->fail('El preventista no debió poder exceder el límite.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credit', $exception->errors());
        }

        $authorization = $credit->authorize(
            customer: $customer,
            amount: '300',
            actor: $supervisor,
            type: CustomerCreditAuthorizationType::OrderConfirmation,
            order: $order,
            overrideReason: 'Pedido urgente autorizado por gerencia.',
        );

        $this->assertNotNull($authorization);
        $this->assertSame('100.0000', $authorization->exceeded_by);
        $this->assertSame($supervisor->id, $authorization->authorized_by);
    }

    public function test_customer_with_financial_history_cannot_be_deleted(): void
    {
        [$supervisor, $customer] = $this->customerWithOpeningDebt('100');

        $this->actingAs($supervisor)
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($customer);
    }

    public function test_credit_summary_and_combined_payment_are_available_through_versioned_api(): void
    {
        [$supervisor, $customer, $receivable] = $this->customerWithOpeningDebt('1000');
        CustomerCreditProfile::factory()->for($customer)->create([
            'credit_limit' => '2500',
            'term_days' => 15,
        ]);

        Sanctum::actingAs($supervisor, ['credits:view']);
        $this->getJson("/api/v1/customers/{$customer->id}/credit")
            ->assertOk()
            ->assertJsonPath('data.profile.status', CustomerCreditStatus::Active->value)
            ->assertJsonPath('data.profile.credit_limit', '2500.0000')
            ->assertJsonPath('data.summary.receivable_balance', '1000');

        Sanctum::actingAs($supervisor, ['payments:record']);
        $this->postJson("/api/v1/customers/{$customer->id}/payments", [
            'amount' => '500',
            'payment_lines' => [
                ['method' => PaymentMethod::Cash->value, 'amount' => '200'],
                ['method' => PaymentMethod::BankTransfer->value, 'amount' => '300', 'reference' => 'API-TRX-300'],
            ],
            'allocations' => [
                ['receivable_id' => $receivable->id, 'amount' => '500'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.amount', '500.0000')
            ->assertJsonCount(2, 'data.payment_lines');

        $this->assertSame('500.0000', $receivable->refresh()->outstanding_amount);
    }

    /** @return array{User, Customer, CustomerReceivable} */
    private function customerWithOpeningDebt(string $amount): array
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($supervisor)->post(route('customers.opening-balances.store', $customer), [
            'amount' => $amount,
            'issued_at' => today()->subDay()->toDateString(),
            'due_date' => today()->toDateString(),
            'notes' => 'Saldo anterior.',
        ])->assertRedirect();

        return [$supervisor, $customer, CustomerReceivable::query()->sole()];
    }
}
