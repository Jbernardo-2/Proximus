<?php

namespace App\Http\Controllers;

use App\CustomerAccountEntryType;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Services\CustomerPaymentReceiptService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerPaymentReceiptController extends Controller
{
    public function show(
        Customer $customer,
        CustomerAccountEntry $payment,
        CustomerPaymentReceiptService $receiptService,
    ): View {
        $this->authorizeCustomerPayment($customer, $payment);

        return view('operations.customers.payment-receipt', [
            ...$receiptService->data($payment),
            'isPdf' => false,
        ]);
    }

    public function download(
        Customer $customer,
        CustomerAccountEntry $payment,
        CustomerPaymentReceiptService $receiptService,
    ): Response {
        $this->authorizeCustomerPayment($customer, $payment);

        return $this->pdfResponse($payment, $receiptService);
    }

    public function publicDownload(
        CustomerAccountEntry $payment,
        CustomerPaymentReceiptService $receiptService,
    ): Response {
        abort_unless($payment->type === CustomerAccountEntryType::CustomerPayment, 404);

        return $this->pdfResponse($payment, $receiptService)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function authorizeCustomerPayment(Customer $customer, CustomerAccountEntry $payment): void
    {
        abort_unless($payment->customer_id === $customer->id, 404);
        Gate::authorize('view', $payment);
    }

    private function pdfResponse(
        CustomerAccountEntry $payment,
        CustomerPaymentReceiptService $receiptService,
    ): Response {
        return response($receiptService->renderPdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receiptService->filename($payment).'"',
        ]);
    }
}
