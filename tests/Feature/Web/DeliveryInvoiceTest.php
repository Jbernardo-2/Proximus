<?php

namespace Tests\Feature\Web;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\CustomerCreditProfile;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\SettlementMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DeliveryInvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registering_the_payment_generates_a_numbered_invoice_and_offers_print_and_share_options(): void
    {
        [$driver, $run, $runOrder] = $this->stopAwaitingPayment();

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '30', 'reference' => null],
                ['method' => SettlementMethod::Credit->value, 'amount' => '20', 'reference' => null],
            ],
            'credit_reason' => 'Paga el viernes.',
        ])->assertSessionHas('delivery_invoice_run_order_id', $runOrder->id);

        $invoiceNumber = $runOrder->settlement()->sole()->invoice_number;
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-000001$/', $invoiceNumber);

        $this->actingAs($driver)
            ->withSession(['delivery_invoice_run_order_id' => $runOrder->id])
            ->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee("Factura {$invoiceNumber} generada")
            ->assertSee('Imprimir tamaño carta')
            ->assertSee('Imprimir ticket 80 mm')
            ->assertSee('Compartir')
            ->assertSee('Terminar')
            ->assertSee(route('delivery-runs.orders.invoice.show', [$run, $runOrder, 'format' => 'ticket', 'print' => 1]));

        $this->actingAs($driver)
            ->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertDontSee('data-invoice-dialog', false);
    }

    public function test_invoice_shows_delivered_products_and_payment_distribution_in_both_formats(): void
    {
        [$driver, $run, $runOrder] = $this->settledStop();

        foreach (['letter', 'ticket'] as $format) {
            $this->actingAs($driver)
                ->get(route('delivery-runs.orders.invoice.show', [$run, $runOrder, 'format' => $format]))
                ->assertOk()
                ->assertSee($runOrder->settlement->invoice_number)
                ->assertSee('Pulpería La Estrella')
                ->assertSee('Arroz precocido')
                ->assertDontSee('Producto devuelto')
                ->assertSee('Efectivo')
                ->assertSee('Crédito')
                ->assertSee('L 50.00')
                ->assertSee('L 30.00')
                ->assertSee('L 20.00')
                ->assertSee('Paga el viernes.')
                ->assertSee('no fiscal');
        }
    }

    public function test_invoice_downloads_as_pdf_in_letter_and_single_page_ticket_formats(): void
    {
        [$driver, $run, $runOrder] = $this->settledStop();

        $letter = $this->actingAs($driver)
            ->get(route('delivery-runs.orders.invoice.download', [$run, $runOrder]));
        $letter->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $letter->getContent());
        $this->assertStringContainsString('612.000 792.000', $letter->getContent());

        $ticket = $this->actingAs($driver)
            ->get(route('delivery-runs.orders.invoice.download', [$run, $runOrder, 'format' => 'ticket']));
        $ticket->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('-ticket.pdf', (string) $ticket->headers->get('content-disposition'));
        $this->assertSame(1, preg_match('/0\.000 0\.000 226\.770 (\d+)\.\d+/', $ticket->getContent(), $mediaBox));
        $this->assertLessThan(1500, (int) $mediaBox[1]);
        $this->assertSame(1, preg_match('/\/Count 1\b/', $ticket->getContent()));
    }

    public function test_invoice_access_is_limited_to_users_who_can_view_the_run_and_signed_public_links(): void
    {
        [, $run, $runOrder] = $this->settledStop();
        $otherDriver = User::factory()->repartidor()->create();
        $unsettledStop = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'visit_order' => 2,
            'status' => DeliveryOrderStatus::Loaded,
        ]);

        $this->get(route('delivery-runs.orders.invoice.show', [$run, $runOrder]))
            ->assertRedirect(route('login'));
        $this->actingAs($otherDriver)
            ->get(route('delivery-runs.orders.invoice.show', [$run, $runOrder]))
            ->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())
            ->get(route('delivery-runs.orders.invoice.show', [$run, $unsettledStop]))
            ->assertNotFound();

        auth()->logout();
        $this->get(route('delivery-invoices.public-download', $runOrder))->assertForbidden();
        $publicResponse = $this->get(URL::temporarySignedRoute(
            'delivery-invoices.public-download',
            now()->addMinute(),
            ['runOrder' => $runOrder],
        ));
        $publicResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $publicResponse->headers->get('cache-control'));
    }

    /** @return array{0: User, 1: DeliveryRun, 2: DeliveryRunOrder} */
    private function settledStop(): array
    {
        [$driver, $run, $runOrder] = $this->stopAwaitingPayment();

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '30', 'reference' => null],
                ['method' => SettlementMethod::Credit->value, 'amount' => '20', 'reference' => null],
            ],
            'credit_reason' => 'Paga el viernes.',
        ])->assertSessionHasNoErrors();
        auth()->logout();

        return [$driver, $run, $runOrder->refresh()->load('settlement')];
    }

    /** @return array{0: User, 1: DeliveryRun, 2: DeliveryRunOrder} */
    private function stopAwaitingPayment(): array
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create([
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'status' => DeliveryRunStatus::InTransit,
        ]);
        $order = Order::factory()->create([
            'status' => OrderStatus::InTransit,
            'customer_name' => 'Pulpería La Estrella',
        ]);
        CustomerCreditProfile::factory()->create([
            'customer_id' => $order->customer_id,
            'credit_limit' => '100000.0000',
            'term_days' => 30,
        ]);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $order->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::PartiallyDelivered,
            'delivered_total' => '50.0000',
            'balance_due' => '50.0000',
            'receiver_name' => 'María López',
            'outcome_recorded_at' => now(),
            'outcome_recorded_by' => $driver->id,
        ]);
        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $runOrder->id,
            'product_name' => 'Arroz precocido',
            'loaded_quantity' => '5.000000',
            'delivered_quantity' => '5.000000',
            'delivered_line_total' => '50.0000',
        ]);
        DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $runOrder->id,
            'product_name' => 'Producto devuelto',
            'loaded_quantity' => '2.000000',
            'returned_quantity' => '2.000000',
        ]);

        return [$driver, $run, $runOrder];
    }
}
