<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\DeliveryInvoiceController as WebDeliveryInvoiceController;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Services\DeliveryInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Invoice of a settled delivery: what to show and the links to print or share it.
 * The PDF comes from the inherited download action (?format=letter|ticket).
 */
class DeliveryInvoiceController extends WebDeliveryInvoiceController
{
    public function details(DeliveryRun $deliveryRun, DeliveryRunOrder $runOrder, DeliveryInvoiceService $invoiceService): JsonResponse
    {
        Gate::authorize('view', $deliveryRun);
        abort_unless($runOrder->settlement()->exists(), 404);
        $data = $invoiceService->data($runOrder);
        $download = fn (string $format): string => route('api.v1.delivery-runs.orders.invoice.download', [$deliveryRun, $runOrder, 'format' => $format]);

        return response()->json(['data' => [
            'invoice_number' => $data['invoiceNumber'],
            'customer_name' => $data['order']->customer_name,
            'total' => $runOrder->delivered_total,
            'currency' => $data['settlement']->currency,
            'settled_at' => $data['settlement']->settled_at?->toIso8601String(),
            'payment_lines' => $data['paymentLines'],
            'download_urls' => [
                DeliveryInvoiceService::FORMAT_LETTER => $download(DeliveryInvoiceService::FORMAT_LETTER),
                DeliveryInvoiceService::FORMAT_TICKET => $download(DeliveryInvoiceService::FORMAT_TICKET),
            ],
            'public_url' => $data['publicDownloadUrl'],
            'whatsapp_url' => $data['whatsappUrl'],
        ]]);
    }
}
