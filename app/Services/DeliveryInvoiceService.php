<?php

namespace App\Services;

use App\DeliveryPaymentStatus;
use App\Models\CompanyProfile;
use App\Models\DeliveryRunOrder;
use App\Models\DeliverySettlementLine;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DeliveryInvoiceService
{
    public const string FORMAT_LETTER = 'letter';

    public const string FORMAT_TICKET = 'ticket';

    private const float TICKET_WIDTH_POINTS = 226.77;

    private const float TICKET_MEASURING_HEIGHT_POINTS = 14400.0;

    public function format(?string $value): string
    {
        return $value === self::FORMAT_TICKET ? self::FORMAT_TICKET : self::FORMAT_LETTER;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(DeliveryRunOrder $runOrder): array
    {
        $runOrder->loadMissing([
            'deliveryRun',
            'order.customer',
            'items',
            'settlement.lines.deliveryPayment',
            'settlement.settledBy',
        ]);
        $settlement = $runOrder->settlement;
        $order = $runOrder->order;
        $company = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $invoiceNumber = $settlement->invoice_number ?: $order->order_number;
        $currencySymbol = $settlement->currency === 'HNL' ? 'L' : $settlement->currency;
        $publicDownloadUrl = URL::temporarySignedRoute(
            'delivery-invoices.public-download',
            now()->addDays(7),
            ['runOrder' => $runOrder],
        );
        $shareText = implode("\n", [
            'Factura '.$invoiceNumber,
            $company->commercial_name,
            'Total: '.$currencySymbol.' '.number_format((float) $runOrder->delivered_total, 2),
            'Descarga tu factura (enlace válido por 7 días):',
            $publicDownloadUrl,
        ]);
        $whatsappNumber = $this->whatsappNumber($order->customer?->whatsapp ?: $order->customer?->phone);

        return [
            'runOrder' => $runOrder,
            'deliveryRun' => $runOrder->deliveryRun,
            'order' => $order,
            'settlement' => $settlement,
            'company' => $company,
            'companyLogoDataUri' => $this->companyLogoDataUri($company),
            'invoiceNumber' => $invoiceNumber,
            'currencySymbol' => $currencySymbol,
            'items' => $runOrder->items
                ->filter(fn ($item): bool => bccomp((string) $item->delivered_quantity, '0', 6) > 0)
                ->values(),
            'paymentLines' => $settlement->lines->map(fn (DeliverySettlementLine $line): array => [
                'label' => $line->method->label(),
                'reference' => $line->reference,
                'receipt_number' => $line->deliveryPayment?->receipt_number,
                'amount' => (string) $line->amount,
                'is_credit' => $line->method->isCredit(),
                'is_voided' => $line->deliveryPayment?->status === DeliveryPaymentStatus::Voided,
            ])->all(),
            'publicDownloadUrl' => $publicDownloadUrl,
            'shareTitle' => 'Factura '.$invoiceNumber,
            'shareText' => 'Factura de '.$company->commercial_name,
            'whatsappUrl' => 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($shareText),
        ];
    }

    /**
     * @return array{invoiceNumber: string, customerName: string, total: string, printLetterUrl: string, printTicketUrl: string, downloadUrl: string, filename: string, whatsappUrl: string, shareTitle: string, shareText: string}
     */
    public function dialogData(DeliveryRunOrder $runOrder): array
    {
        $data = $this->data($runOrder);
        $routeParameters = [$data['deliveryRun'], $runOrder];

        return [
            'invoiceNumber' => $data['invoiceNumber'],
            'customerName' => $data['order']->customer_name,
            'total' => $data['currencySymbol'].' '.number_format((float) $runOrder->delivered_total, 2),
            'printLetterUrl' => route('delivery-runs.orders.invoice.show', [...$routeParameters, 'print' => 1]),
            'printTicketUrl' => route('delivery-runs.orders.invoice.show', [
                ...$routeParameters,
                'format' => self::FORMAT_TICKET,
                'print' => 1,
            ]),
            'downloadUrl' => route('delivery-runs.orders.invoice.download', $routeParameters),
            'filename' => $this->filename($runOrder, self::FORMAT_LETTER),
            'whatsappUrl' => $data['whatsappUrl'],
            'shareTitle' => $data['shareTitle'],
            'shareText' => $data['shareText'],
        ];
    }

    public function renderPdf(DeliveryRunOrder $runOrder, string $format): string
    {
        $html = view('deliveries.invoice', [
            ...$this->data($runOrder),
            'format' => $format,
            'isPdf' => true,
            'autoPrint' => false,
        ])->render();

        if ($format !== self::FORMAT_TICKET) {
            return $this->dompdf($html, 'letter')->output();
        }

        $contentHeight = 0.0;
        $measuring = $this->dompdf($html, [0, 0, self::TICKET_WIDTH_POINTS, self::TICKET_MEASURING_HEIGHT_POINTS], [[
            'event' => 'end_frame',
            'f' => function (Frame $frame) use (&$contentHeight): void {
                if (mb_strtolower($frame->get_node()->nodeName) === 'body') {
                    $contentHeight = (float) $frame->get_border_box()['h'];
                }
            },
        ]]);
        unset($measuring);

        return $this->dompdf($html, [0, 0, self::TICKET_WIDTH_POINTS, max($contentHeight, 200.0) + 24.0])->output();
    }

    public function filename(DeliveryRunOrder $runOrder, string $format): string
    {
        $runOrder->loadMissing(['settlement', 'order']);
        $invoiceNumber = Str::slug($runOrder->settlement->invoice_number ?: $runOrder->order->order_number) ?: 'entrega';
        $suffix = $format === self::FORMAT_TICKET ? '-ticket' : '';

        return "factura-{$invoiceNumber}{$suffix}.pdf";
    }

    /**
     * @param  string|array{0: int|float, 1: int|float, 2: int|float, 3: int|float}  $paper
     * @param  list<array{event: string, f: callable}>  $callbacks
     */
    private function dompdf(string $html, string|array $paper, array $callbacks = []): Dompdf
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setCallbacks($callbacks);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    private function companyLogoDataUri(CompanyProfile $company): ?string
    {
        if ($company->logo_path === null || ! Storage::disk('public')->exists($company->logo_path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($company->logo_path)
            .';base64,'.base64_encode(Storage::disk('public')->get($company->logo_path));
    }

    private function whatsappNumber(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return mb_strlen($digits) === 8 ? '504'.$digits : $digits;
    }
}
