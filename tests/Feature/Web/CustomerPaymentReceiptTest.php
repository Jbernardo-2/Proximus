<?php

namespace Tests\Feature\Web;

use App\Actions\RecordCustomerPaymentAction;
use App\CustomerAccountEntryType;
use App\CustomerReceivableStatus;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerPaymentReceiptTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_combined_payment_redirects_to_an_immutable_receipt(): void
    {
        $supervisor = User::factory()->supervisor()->create(['name' => 'Ana Supervisora']);
        [$customer, $receivable] = $this->customerWithDebt('2300');
        CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID)->update([
            'commercial_name' => 'Distribuidora Original',
            'rtn' => '08011999123456',
        ]);

        $response = $this->actingAs($supervisor)->post(route('customers.payments.store', $customer), [
            'amount' => '1500',
            'payment_lines' => [
                ['method' => PaymentMethod::Cash->value, 'amount' => '500', 'reference' => null],
                ['method' => PaymentMethod::BankTransfer->value, 'amount' => '1000', 'reference' => 'TRX-1000'],
            ],
            'allocations' => [
                ['receivable_id' => $receivable->id, 'amount' => '1500'],
            ],
            'notes' => 'Abono recibido <script>alert("xss")</script>',
        ]);

        $payment = CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment->value)
            ->sole();
        $response->assertRedirect(route('customers.payments.receipt.show', [$customer, $payment]));
        $this->assertSame('Distribuidora Original', $payment->receipt_snapshot['company']['commercial_name']);
        $this->assertSame('Pulpería La Estrella', $payment->receipt_snapshot['customer']['business_name']);
        $this->assertSame('2300.0000', $payment->receipt_snapshot['balances']['before']);
        $this->assertSame('800.0000', $payment->receipt_snapshot['balances']['after']);
        $this->assertCount(2, $payment->receipt_snapshot['payment']['methods']);

        $customer->update(['business_name' => 'Nombre cambiado']);
        CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID)->update([
            'commercial_name' => 'Empresa cambiada',
        ]);

        $this->actingAs($supervisor)
            ->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertOk()
            ->assertSee('Distribuidora Original')
            ->assertDontSee('Empresa cambiada')
            ->assertSee('Pulpería La Estrella')
            ->assertDontSee('Nombre cambiado')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia bancaria')
            ->assertSee('L 2,300.00')
            ->assertSee('L 800.00')
            ->assertSee('Compartir por WhatsApp')
            ->assertSee('https://wa.me/50498765432', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_authorized_download_and_temporary_public_link_return_pdf(): void
    {
        [$supervisor, $customer, $payment] = $this->recordPayment();

        $privateResponse = $this->actingAs($supervisor)
            ->get(route('customers.payments.receipt.download', [$customer, $payment]));

        $privateResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $privateResponse->getContent());

        $this->get(route('payment-receipts.public-download', $payment))->assertForbidden();

        $expiredUrl = URL::temporarySignedRoute(
            'payment-receipts.public-download',
            now()->subMinute(),
            ['payment' => $payment],
        );
        $this->get($expiredUrl)->assertForbidden();

        $signedUrl = URL::temporarySignedRoute(
            'payment-receipts.public-download',
            now()->addMinute(),
            ['payment' => $payment],
        );
        $publicResponse = $this->get($signedUrl);
        $publicResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $publicResponse->headers->get('cache-control'));
        $this->assertStringStartsWith('%PDF-', $publicResponse->getContent());
    }

    public function test_guest_cannot_open_the_private_receipt_page(): void
    {
        [, $customer, $payment] = $this->recordPayment();

        $this->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertRedirect(route('login'));
    }

    public function test_receipt_access_respects_role_and_customer_boundaries(): void
    {
        [$supervisor, $customer, $payment] = $this->recordPayment();
        $preventista = User::factory()->preventista()->create();
        $warehouseUser = User::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->actingAs($preventista)
            ->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertOk();
        $this->actingAs($warehouseUser)
            ->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertForbidden();
        $this->actingAs($supervisor)
            ->get(route('customers.payments.receipt.show', [$otherCustomer, $payment]))
            ->assertNotFound();
    }

    public function test_reversed_payment_receipt_is_preserved_and_marked_as_cancelled(): void
    {
        [$supervisor, $customer, $payment] = $this->recordPayment();

        $this->actingAs($supervisor)->post(route('customers.payments.reverse', [$customer, $payment]), [
            'reason' => 'El efectivo entregado no coincidía con el monto registrado.',
        ])->assertRedirect(route('customers.show', $customer).'#account');

        $this->actingAs($supervisor)
            ->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertOk()
            ->assertSee('ANULADO')
            ->assertSee('El efectivo entregado no coincidía con el monto registrado.')
            ->assertSee('Documento de reversión');
    }

    public function test_payment_created_before_snapshots_still_has_a_reconstructed_receipt(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = Customer::factory()->create();
        $payment = CustomerAccountEntry::factory()->for($customer)->create([
            'type' => CustomerAccountEntryType::CustomerPayment,
            'receipt_number' => 'ABO-2026-000001',
            'method' => PaymentMethod::Cash,
            'credit_amount' => '250.0000',
            'debit_amount' => '0.0000',
            'receipt_snapshot' => null,
            'created_by' => $supervisor->id,
        ]);

        $this->actingAs($supervisor)
            ->get(route('customers.payments.receipt.show', [$customer, $payment]))
            ->assertOk()
            ->assertSee('ABO-2026-000001')
            ->assertSee('Efectivo')
            ->assertSee('comprobante corresponde a un abono anterior');
    }

    /** @return array{0: User, 1: Customer, 2: CustomerAccountEntry} */
    private function recordPayment(): array
    {
        $supervisor = User::factory()->supervisor()->create();
        [$customer, $receivable] = $this->customerWithDebt('1000');
        $payment = app(RecordCustomerPaymentAction::class)->handle($customer, [
            'amount' => '400',
            'payment_lines' => [
                ['method' => PaymentMethod::Cash->value, 'amount' => '400', 'reference' => null],
            ],
            'allocations' => [
                ['receivable_id' => $receivable->id, 'amount' => '400'],
            ],
            'notes' => 'Abono de prueba.',
        ], $supervisor);

        return [$supervisor, $customer, $payment];
    }

    /** @return array{0: Customer, 1: CustomerReceivable} */
    private function customerWithDebt(string $amount): array
    {
        $customer = Customer::factory()->create([
            'code' => 'CLI-REC-001',
            'business_name' => 'Pulpería La Estrella',
            'contact_name' => 'María López',
            'phone' => '2222-3333',
            'whatsapp' => '9876-5432',
            'address' => 'Barrio El Centro',
        ]);
        $receivable = CustomerReceivable::factory()->for($customer)->create([
            'source' => 'opening_balance',
            'order_id' => null,
            'delivery_run_order_id' => null,
            'document_number' => 'SALDO-001',
            'original_amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => CustomerReceivableStatus::Open,
        ]);

        return [$customer, $receivable];
    }
}
