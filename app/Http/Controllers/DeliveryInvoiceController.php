<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Services\DeliveryInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeliveryInvoiceController extends Controller
{
    public function show(
        Request $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        DeliveryInvoiceService $invoiceService,
    ): View {
        $this->authorizeInvoice($deliveryRun, $runOrder);

        $format = $invoiceService->format($request->query('format'));

        return view('deliveries.invoice', [
            ...$invoiceService->data($runOrder),
            'format' => $format,
            'filename' => $invoiceService->filename($runOrder, $format),
            'isPdf' => false,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    public function download(
        Request $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        DeliveryInvoiceService $invoiceService,
    ): Response {
        $this->authorizeInvoice($deliveryRun, $runOrder);

        return $this->pdfResponse($runOrder, $invoiceService, $invoiceService->format($request->query('format')));
    }

    public function publicDownload(
        Request $request,
        DeliveryRunOrder $runOrder,
        DeliveryInvoiceService $invoiceService,
    ): Response {
        abort_unless($runOrder->settlement()->exists(), 404);

        return $this->pdfResponse($runOrder, $invoiceService, $invoiceService->format($request->query('format')))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function authorizeInvoice(DeliveryRun $deliveryRun, DeliveryRunOrder $runOrder): void
    {
        Gate::authorize('view', $deliveryRun);
        abort_unless($runOrder->settlement()->exists(), 404);
    }

    private function pdfResponse(
        DeliveryRunOrder $runOrder,
        DeliveryInvoiceService $invoiceService,
        string $format,
    ): Response {
        return response($invoiceService->renderPdf($runOrder, $format), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoiceService->filename($runOrder, $format).'"',
        ]);
    }
}
