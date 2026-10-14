<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\CustomerPaymentReceiptController as WebCustomerPaymentReceiptController;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Services\CustomerPaymentReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Account payment receipt: its frozen content and the links to share it.
 * The PDF comes from the inherited download action.
 */
class CustomerPaymentReceiptController extends WebCustomerPaymentReceiptController
{
    public function details(Customer $customer, CustomerAccountEntry $payment, CustomerPaymentReceiptService $receiptService): JsonResponse
    {
        abort_unless($payment->customer_id === $customer->id, 404);
        Gate::authorize('view', $payment);
        $data = $receiptService->data($payment);

        return response()->json(['data' => [
            'receipt' => $data['receipt'],
            'is_reversed' => $payment->reversed_at !== null,
            'download_url' => route('api.v1.customers.payments.receipt.download', [$customer, $payment]),
            'public_url' => $data['publicDownloadUrl'],
            'whatsapp_url' => $data['whatsappUrl'],
        ]]);
    }
}
