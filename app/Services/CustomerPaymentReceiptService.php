<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\CustomerAccountEntry;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CustomerPaymentReceiptService
{
    /**
     * @return array<string, mixed>
     */
    public function data(CustomerAccountEntry $payment): array
    {
        $payment->loadMissing([
            'customer',
            'paymentLines',
            'allocations.receivable.order',
            'createdBy',
            'reversedBy',
            'reversal.createdBy',
        ]);
        $snapshot = $payment->receipt_snapshot ?? $this->legacySnapshot($payment);
        $publicDownloadUrl = URL::temporarySignedRoute(
            'payment-receipts.public-download',
            now()->addDays(7),
            ['payment' => $payment],
        );
        $shareText = implode("\n", [
            'Comprobante de abono '.$snapshot['payment']['receipt_number'],
            $snapshot['company']['commercial_name'],
            'Monto recibido: L '.number_format((float) $snapshot['payment']['amount'], 2),
            'Descarga tu comprobante (enlace válido por 7 días):',
            $publicDownloadUrl,
        ]);
        $whatsappNumber = $this->whatsappNumber(
            $snapshot['customer']['whatsapp'] ?: $snapshot['customer']['phone'],
        );

        return [
            'payment' => $payment,
            'receipt' => $snapshot,
            'occurredAt' => CarbonImmutable::parse($snapshot['payment']['occurred_at']),
            'companyLogoDataUri' => $this->companyLogoDataUri(),
            'publicDownloadUrl' => $publicDownloadUrl,
            'whatsappUrl' => 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($shareText),
            'filename' => $this->filename($payment),
            'isReconstructed' => $payment->receipt_snapshot === null,
        ];
    }

    public function renderPdf(CustomerAccountEntry $payment): string
    {
        $html = view('operations.customers.payment-receipt', [
            ...$this->data($payment),
            'isPdf' => true,
        ])->render();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(CustomerAccountEntry $payment): string
    {
        $receiptNumber = Str::slug($payment->receipt_number ?? $payment->id) ?: 'abono';

        return "comprobante-{$receiptNumber}.pdf";
    }

    /** @return array<string, mixed> */
    private function legacySnapshot(CustomerAccountEntry $payment): array
    {
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $paymentMethods = $payment->paymentLines->map(fn ($line): array => [
            'method' => $line->method->value,
            'label' => $line->method->label(),
            'amount' => (string) $line->amount,
            'reference' => $line->reference,
        ])->all();

        if ($paymentMethods === [] && $payment->method !== null) {
            $paymentMethods[] = [
                'method' => $payment->method->value,
                'label' => $payment->method->label(),
                'amount' => (string) $payment->credit_amount,
                'reference' => $payment->reference,
            ];
        }

        return [
            'version' => 0,
            'company' => [
                'commercial_name' => $companyProfile->commercial_name,
                'legal_name' => $companyProfile->legal_name,
                'rtn' => $companyProfile->rtn,
                'phone' => $companyProfile->phone,
                'whatsapp' => $companyProfile->whatsapp,
                'email' => $companyProfile->email,
                'address' => $companyProfile->address,
                'slogan' => $companyProfile->slogan,
            ],
            'customer' => [
                'code' => $payment->customer->code,
                'business_name' => $payment->customer->business_name,
                'contact_name' => $payment->customer->contact_name,
                'phone' => $payment->customer->phone,
                'whatsapp' => $payment->customer->whatsapp,
                'address' => $payment->customer->address,
            ],
            'payment' => [
                'receipt_number' => $payment->receipt_number,
                'occurred_at' => $payment->occurred_at->toIso8601String(),
                'currency' => $payment->currency,
                'amount' => (string) $payment->credit_amount,
                'notes' => $payment->notes,
                'received_by' => $payment->createdBy->name,
                'methods' => $paymentMethods,
            ],
            'balances' => [
                'before' => null,
                'after' => null,
            ],
            'allocations' => $payment->allocations->map(fn ($allocation): array => [
                'receivable_id' => $allocation->receivable_id,
                'document_number' => $allocation->receivable->order?->order_number
                    ?: $allocation->receivable->document_number,
                'source' => $allocation->receivable->source->value,
                'source_label' => $allocation->receivable->source->label(),
                'original_amount' => (string) $allocation->receivable->original_amount,
                'balance_before' => null,
                'amount' => (string) $allocation->amount,
                'balance_after' => null,
                'due_date' => $allocation->receivable->due_date?->toDateString(),
            ])->all(),
        ];
    }

    private function companyLogoDataUri(): ?string
    {
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);

        if ($companyProfile->logo_path === null || ! Storage::disk('public')->exists($companyProfile->logo_path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($companyProfile->logo_path)
            .';base64,'.base64_encode(Storage::disk('public')->get($companyProfile->logo_path));
    }

    private function whatsappNumber(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return mb_strlen($digits) === 8 ? '504'.$digits : $digits;
    }
}
