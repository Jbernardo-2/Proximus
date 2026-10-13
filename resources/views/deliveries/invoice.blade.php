<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $invoiceNumber }} · Proximus</title>
    @php
        $isTicket = $format === \App\Services\DeliveryInvoiceService::FORMAT_TICKET;
        $money = static fn ($value): string => $currencySymbol.' '.number_format((float) $value, 2);
        $quantity = static function ($value): string {
            $formatted = (string) $value;

            return str_contains($formatted, '.')
                ? (rtrim(rtrim($formatted, '0'), '.') ?: '0')
                : $formatted;
        };
        $hasCredit = bccomp((string) $runOrder->balance_due, '0', 4) > 0;
        $invoiceUrl = static fn (string $target, array $query = []): string => route(
            'delivery-runs.orders.invoice.'.$target,
            [$deliveryRun, $runOrder, ...$query],
        );
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f5f1; color: #1c241f; font-family: "DejaVu Sans", Arial, sans-serif; line-height: 1.45; }
        p, h1, h2, h3 { margin: 0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; justify-content: center; gap: 9px; padding: 12px; background: #17251d; }
        .button { display: inline-block; border: 1px solid transparent; border-radius: 8px; padding: 9px 14px; font: 700 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .button-primary { background: #f5a524; color: #17251d; }
        .button-secondary { border-color: #d5ddd8; background: #fff; color: #17251d; }
        .button-active { border-color: #f5a524; background: #fff4dc; color: #17251d; }
        .button-whatsapp { background: #25d366; color: #0c321c; }
        .muted { color: #647068; }
        .right { text-align: right; }
        .center { text-align: center; }
        .number { text-align: right; white-space: nowrap; }
        .voided { color: #991b1b; text-decoration: line-through; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
        }

        @if($isTicket)
            @page { margin: 0; }
            @unless($isPdf)
                @page { size: 80mm auto; }
            @endunless
            body { font-size: 9px; }
            .sheet { width: 80mm; margin: 14px auto; background: #fff; padding: 4mm; box-shadow: 0 8px 24px rgba(23, 37, 29, .12); }
            .logo { max-width: 34mm; max-height: 18mm; }
            .brand { font-size: 13px; font-weight: 800; }
            .title { margin-top: 2px; font-size: 11px; font-weight: 800; }
            .rule { margin: 5px 0; border-top: 1px dashed #1c241f; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 1px 0; vertical-align: top; text-align: left; }
            th { border-bottom: 1px solid #1c241f; font-size: 8px; }
            .total td { padding-top: 3px; font-size: 12px; font-weight: 800; }
            .signature { margin-top: 26px; border-top: 1px solid #1c241f; padding-top: 2px; text-align: center; }
            .footer { margin-top: 8px; font-size: 8px; text-align: center; }
            @if($isPdf)
                body { background: #fff; }
                .sheet { width: auto; margin: 0; box-shadow: none; }
            @endif
            @media print {
                .sheet { width: 80mm; margin: 0; box-shadow: none; }
            }
        @else
            @page { margin: 14mm 13mm 16mm; size: letter portrait; }
            body { font-size: 10px; }
            .sheet { max-width: 190mm; min-height: 245mm; margin: 18px auto; border: 1px solid #d9e2dc; border-radius: 10px; background: #fff; padding: 12mm; box-shadow: 0 12px 35px rgba(23, 37, 29, .10); }
            .header { width: 100%; border-bottom: 2px solid #2d6a4f; padding-bottom: 9px; }
            .header td { vertical-align: top; }
            .logo { width: 74px; max-height: 58px; object-fit: contain; }
            .brand { color: #173e2b; font-size: 19px; font-weight: 800; }
            .title { margin-top: 15px; color: #173e2b; font-size: 20px; }
            .invoice-number { margin-top: 3px; color: #2d6a4f; font-size: 13px; font-weight: 800; }
            .grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
            .grid td { width: 50%; border: 1px solid #d9e2dc; border-radius: 6px; padding: 7px; vertical-align: top; }
            .label { display: block; color: #69756d; font-size: 7px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .value { display: block; margin-top: 2px; font-size: 10px; font-weight: 700; }
            .section-title { margin: 15px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #aebfb4; color: #173e2b; font-size: 13px; }
            table.data { width: 100%; border-collapse: collapse; }
            table.data thead { display: table-header-group; }
            table.data th { background: #eaf2ed; color: #173e2b; font-size: 8px; text-align: left; }
            table.data th, table.data td { border: 1px solid #cbd8cf; padding: 6px; vertical-align: top; }
            table.data th.number { text-align: right; }
            table.data .total td { background: #eef8f1; color: #17633d; font-size: 13px; font-weight: 800; }
            .credit { margin-top: 12px; border: 1px solid #e4c66b; border-radius: 7px; background: #fff9df; padding: 8px; color: #6f5710; }
            .signatures { width: 100%; margin-top: 34px; }
            .signatures td { width: 50%; padding: 0 22px; text-align: center; }
            .signature-line { padding-top: 5px; border-top: 1px solid #5f6d64; }
            .footer { margin-top: 20px; padding-top: 7px; border-top: 1px solid #d3ddd6; color: #6c776f; font-size: 8px; text-align: center; }
            @media print {
                .sheet { max-width: none; min-height: auto; margin: 0; border: 0; border-radius: 0; padding: 0; box-shadow: none; }
            }
        @endif
    </style>
</head>
<body>
    @unless($isPdf)
        <nav class="toolbar no-print" aria-label="Acciones de la factura">
            <a class="button button-secondary" href="{{ route('delivery-runs.show', $deliveryRun) }}">Volver a la jornada</a>
            <a class="button {{ $isTicket ? 'button-secondary' : 'button-active' }}" href="{{ $invoiceUrl('show') }}">Tamaño carta</a>
            <a class="button {{ $isTicket ? 'button-active' : 'button-secondary' }}" href="{{ $invoiceUrl('show', ['format' => 'ticket']) }}">Ticket 80 mm</a>
            <button class="button button-secondary" type="button" onclick="window.print()">Imprimir</button>
            <a class="button button-primary" href="{{ $invoiceUrl('download', ['format' => $format]) }}">Descargar PDF</a>
            <a
                class="button button-whatsapp"
                href="{{ $whatsappUrl }}"
                target="_blank"
                rel="noopener"
                data-share-invoice
                data-download-url="{{ $invoiceUrl('download', ['format' => $format]) }}"
                data-filename="{{ $filename }}"
                data-share-title="{{ $shareTitle }}"
                data-share-text="{{ $shareText }}"
            >Compartir</a>
        </nav>
    @endunless

    <main class="sheet">
        @if($isTicket)
            <div class="center">
                @if($companyLogoDataUri)<img class="logo" src="{{ $companyLogoDataUri }}" alt="">@endif
                <p class="brand">{{ $company->commercial_name }}</p>
                @if($company->legal_name)<p>{{ $company->legal_name }}</p>@endif
                @if($company->rtn)<p>RTN: {{ $company->rtn }}</p>@endif
                @if($company->address)<p>{{ $company->address }}</p>@endif
                @if($company->phone)<p>Tel. {{ $company->phone }}</p>@endif
            </div>
            <div class="rule"></div>
            <p class="title center">FACTURA {{ $invoiceNumber }}</p>
            <p class="center">{{ $settlement->settled_at->format('d/m/Y H:i') }}</p>
            <div class="rule"></div>
            <p><strong>Cliente:</strong> {{ $order->customer_name }}</p>
            @if($order->customer_code)<p><strong>Código:</strong> {{ $order->customer_code }}</p>@endif
            @if($order->customer_address)<p><strong>Dirección:</strong> {{ $order->customer_address }}</p>@endif
            <p><strong>Pedido:</strong> {{ $order->order_number }} · {{ $deliveryRun->run_number }}</p>
            <p><strong>Atendió:</strong> {{ $settlement->settledBy->name }}</p>
            @if($runOrder->receiver_name)<p><strong>Recibió:</strong> {{ $runOrder->receiver_name }}</p>@endif
            <div class="rule"></div>
            <table>
                <thead><tr><th>Cant.</th><th>Descripción</th><th class="number">Importe</th></tr></thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td style="width: 12mm;">{{ $quantity($item->delivered_quantity) }}</td>
                            <td>{{ $item->product_name }}<br><span class="muted">{{ $item->presentation_name }} × {{ $money($item->unit_price) }}</span></td>
                            <td class="number">{{ $money($item->delivered_line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="rule"></div>
            <table>
                <tr class="total"><td>TOTAL</td><td class="number">{{ $money($runOrder->delivered_total) }}</td></tr>
                @foreach($paymentLines as $line)
                    <tr>
                        <td class="{{ $line['is_voided'] ? 'voided' : '' }}">{{ $line['label'] }}@if($line['reference']) · Ref. {{ $line['reference'] }}@endif @if($line['is_voided'])(anulado)@endif</td>
                        <td class="number {{ $line['is_voided'] ? 'voided' : '' }}">{{ $money($line['amount']) }}</td>
                    </tr>
                @endforeach
                <tr><td><strong>Pagado</strong></td><td class="number"><strong>{{ $money($runOrder->collected_total) }}</strong></td></tr>
                <tr><td><strong>Saldo pendiente</strong></td><td class="number"><strong>{{ $money($runOrder->balance_due) }}</strong></td></tr>
            </table>
            @if($hasCredit && $runOrder->credit_reason)
                <p style="margin-top: 4px;"><strong>Crédito:</strong> {{ $runOrder->credit_reason }}</p>
            @endif
            <div class="signature">Firma del cliente</div>
            <p class="footer">Documento interno no fiscal<br>¡Gracias por su compra!</p>
        @else
            <table class="header">
                <tr>
                    <td style="width: 82px;">
                        @if($companyLogoDataUri)<img class="logo" src="{{ $companyLogoDataUri }}" alt="">@endif
                    </td>
                    <td>
                        <p class="brand">{{ $company->commercial_name }}</p>
                        @if($company->legal_name)<p>{{ $company->legal_name }}</p>@endif
                        @if($company->rtn)<p class="muted">RTN: {{ $company->rtn }}</p>@endif
                        @if($company->address)<p class="muted">{{ $company->address }}</p>@endif
                    </td>
                    <td class="right" style="width: 180px;">
                        @if($company->phone)<p>Tel. {{ $company->phone }}</p>@endif
                        @if($company->whatsapp)<p>WhatsApp {{ $company->whatsapp }}</p>@endif
                        @if($company->email)<p>{{ $company->email }}</p>@endif
                        @if($company->slogan)<p class="muted">{{ $company->slogan }}</p>@endif
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-top: 12px;"><tr>
                <td>
                    <h1 class="title">Factura</h1>
                    <p class="invoice-number">{{ $invoiceNumber }}</p>
                </td>
                <td class="right">
                    <span class="label">Fecha y hora</span><span class="value">{{ $settlement->settled_at->format('d/m/Y H:i') }}</span>
                    <span class="label" style="margin-top: 7px;">Pedido · Jornada</span><span class="value">{{ $order->order_number }} · {{ $deliveryRun->run_number }}</span>
                </td>
            </tr></table>

            <table class="grid">
                <tr>
                    <td><span class="label">Cliente</span><span class="value">{{ $order->customer_name }}</span><span class="muted">{{ $order->customer_code }}</span></td>
                    <td><span class="label">Condición de pago</span><span class="value">{{ $hasCredit ? 'Crédito' : 'Contado' }}</span><span class="muted">{{ $runOrder->status->label() }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Dirección</span><span class="value">{{ $order->customer_address ?: 'No registrada' }}</span></td>
                    <td><span class="label">Entregado por</span><span class="value">{{ $settlement->settledBy->name }}</span><span class="muted">Recibió: {{ $runOrder->receiver_name ?: 'No registrado' }}</span></td>
                </tr>
            </table>

            <h2 class="section-title">Productos entregados</h2>
            <table class="data">
                <thead><tr><th>Código</th><th>Descripción</th><th class="number">Cantidad</th><th class="number">Precio</th><th class="number">Importe</th></tr></thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $item->product_sku }}</td>
                            <td><strong>{{ $item->product_name }}</strong><br><span class="muted">{{ $item->presentation_name }}</span></td>
                            <td class="number">{{ $quantity($item->delivered_quantity) }}</td>
                            <td class="number">{{ $money($item->unit_price) }}</td>
                            <td class="number">{{ $money($item->delivered_line_total) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total"><td colspan="4" class="number">TOTAL</td><td class="number">{{ $money($runOrder->delivered_total) }}</td></tr>
                </tbody>
            </table>

            <h2 class="section-title">Forma de pago</h2>
            <table class="data">
                <thead><tr><th>Método</th><th>Referencia</th><th>Recibo</th><th class="number">Monto</th></tr></thead>
                <tbody>
                    @foreach($paymentLines as $line)
                        <tr>
                            <td><strong class="{{ $line['is_voided'] ? 'voided' : '' }}">{{ $line['label'] }}</strong>@if($line['is_voided']) <span class="muted">(anulado)</span>@endif</td>
                            <td>{{ $line['reference'] ?: '—' }}</td>
                            <td>{{ $line['receipt_number'] ?: '—' }}</td>
                            <td class="number {{ $line['is_voided'] ? 'voided' : '' }}">{{ $money($line['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="number"><strong>Pagado</strong></td><td class="number"><strong>{{ $money($runOrder->collected_total) }}</strong></td></tr>
                    <tr><td colspan="3" class="number"><strong>Saldo pendiente</strong></td><td class="number"><strong>{{ $money($runOrder->balance_due) }}</strong></td></tr>
                </tbody>
            </table>

            @if($hasCredit && $runOrder->credit_reason)
                <div class="credit"><strong>Acuerdo de crédito:</strong> {{ $runOrder->credit_reason }}</div>
            @endif

            <table class="signatures"><tr>
                <td><div class="signature-line">Entregado por</div></td>
                <td><div class="signature-line">Firma del cliente</div></td>
            </tr></table>

            <footer class="footer">Generado con Proximus · Factura interna no fiscal · Conserve este documento para futuras consultas.</footer>
        @endif
    </main>

    @unless($isPdf)
        <script>
            document.querySelector('[data-share-invoice]')?.addEventListener('click', async function (event) {
                event.preventDefault();
                const button = event.currentTarget;
                const originalText = button.textContent;
                const downloadFallback = () => {
                    const link = document.createElement('a');
                    link.href = button.dataset.downloadUrl;
                    link.download = button.dataset.filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                };

                if (!navigator.share || !navigator.canShare) {
                    downloadFallback();
                    window.open(button.href, '_blank', 'noopener');
                    return;
                }

                button.textContent = 'Preparando factura…';

                try {
                    const response = await fetch(button.dataset.downloadUrl, { credentials: 'same-origin' });

                    if (!response.ok) {
                        throw new Error('No se pudo generar la factura.');
                    }

                    const file = new File([await response.blob()], button.dataset.filename, { type: 'application/pdf' });

                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({
                            files: [file],
                            title: button.dataset.shareTitle,
                            text: button.dataset.shareText,
                        });
                    } else {
                        downloadFallback();
                        window.location.href = button.href;
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        downloadFallback();
                        window.location.href = button.href;
                    }
                } finally {
                    button.textContent = originalText;
                }
            });

            @if($autoPrint)
                window.addEventListener('load', () => window.print());
            @endif
        </script>
    @endunless
</body>
</html>
