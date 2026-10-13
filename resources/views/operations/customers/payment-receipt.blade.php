<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-app-icons />
    <title>Comprobante {{ $receipt['payment']['receipt_number'] }} · Proximus</title>
    <style>
        @page { margin: 14mm 13mm 16mm; size: letter portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f5f1; color: #1c241f; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; line-height: 1.45; }
        p, h1, h2, h3 { margin: 0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; justify-content: center; gap: 9px; padding: 12px; background: #17251d; }
        .button { display: inline-block; border: 1px solid transparent; border-radius: 8px; padding: 9px 14px; font: 700 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .button-primary { background: #f5a524; color: #17251d; }
        .button-secondary { border-color: #d5ddd8; background: #fff; color: #17251d; }
        .button-whatsapp { background: #25d366; color: #0c321c; }
        .notice { max-width: 190mm; margin: 12px auto 0; border: 1px solid #9bd2ae; border-radius: 8px; background: #eaf8ef; padding: 10px 12px; color: #185a35; font: 700 12px Arial, sans-serif; }
        .sheet { position: relative; max-width: 190mm; min-height: 245mm; margin: 18px auto; overflow: hidden; border: 1px solid #d9e2dc; border-radius: 10px; background: #fff; padding: 12mm; box-shadow: 0 12px 35px rgba(23, 37, 29, .10); }
        .header { width: 100%; border-bottom: 2px solid #2d6a4f; padding-bottom: 9px; }
        .header td { vertical-align: top; }
        .logo { width: 74px; max-height: 58px; object-fit: contain; }
        .brand { color: #173e2b; font-size: 19px; font-weight: 800; }
        .muted { color: #647068; }
        .right { text-align: right; }
        .receipt-title { margin-top: 15px; color: #173e2b; font-size: 20px; }
        .receipt-number { margin-top: 3px; color: #2d6a4f; font-size: 13px; font-weight: 800; }
        .status { display: inline-block; margin-top: 5px; border-radius: 999px; padding: 3px 8px; background: #e7f4eb; color: #23603f; font-weight: 800; }
        .status-reversed { background: #fee2e2; color: #991b1b; }
        .cancelled { position: absolute; top: 48%; left: 12%; z-index: 0; transform: rotate(-24deg); border: 8px solid rgba(185, 28, 28, .16); padding: 8px 28px; color: rgba(185, 28, 28, .18); font-size: 54px; font-weight: 900; letter-spacing: .08em; }
        .content { position: relative; z-index: 1; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
        .grid td { width: 50%; border: 1px solid #d9e2dc; border-radius: 6px; padding: 7px; vertical-align: top; }
        .label { display: block; color: #69756d; font-size: 7px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-size: 10px; font-weight: 700; }
        .amount-box { margin-top: 12px; border: 1px solid #a7d2b5; border-radius: 8px; background: #eef8f1; padding: 10px; text-align: center; }
        .amount-box strong { display: block; margin-top: 2px; color: #17633d; font-size: 25px; }
        .section-title { margin: 15px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #aebfb4; color: #173e2b; font-size: 13px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #eaf2ed; color: #173e2b; font-size: 8px; text-align: left; }
        table.data th, table.data td { border: 1px solid #cbd8cf; padding: 6px; vertical-align: top; }
        table.data .number { text-align: right; white-space: nowrap; }
        .balance { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
        .balance td { width: 50%; border-radius: 7px; padding: 9px; text-align: center; }
        .balance-before { background: #fff7e8; color: #845614; }
        .balance-after { background: #edf7f0; color: #185d38; }
        .balance strong { display: block; margin-top: 2px; font-size: 17px; }
        .reversal { margin-top: 13px; border: 2px solid #ef9a9a; border-radius: 8px; background: #fff1f1; padding: 10px; color: #8d1919; }
        .legacy { margin-top: 12px; border: 1px solid #e4c66b; border-radius: 7px; background: #fff9df; padding: 8px; color: #6f5710; }
        .notes { margin-top: 12px; border-radius: 7px; background: #f5f6f4; padding: 9px; white-space: pre-line; }
        .signatures { width: 100%; margin-top: 28px; }
        .signatures td { width: 50%; padding: 0 22px; text-align: center; }
        .signature-line { padding-top: 5px; border-top: 1px solid #5f6d64; }
        .footer { margin-top: 20px; padding-top: 7px; border-top: 1px solid #d3ddd6; color: #6c776f; font-size: 8px; text-align: center; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { max-width: none; min-height: auto; margin: 0; border: 0; border-radius: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    @php
        $money = static fn ($value): string => 'L '.number_format((float) $value, 2);
        $isReversed = $payment->status === \App\CustomerAccountEntryStatus::Reversed;
    @endphp

    @unless($isPdf)
        <nav class="toolbar no-print" aria-label="Acciones del comprobante">
            <a class="button button-secondary" href="{{ route('customers.show', $payment->customer) }}#account">Volver al cliente</a>
            <button class="button button-secondary" type="button" onclick="window.print()">Imprimir</button>
            <a class="button button-primary" href="{{ route('customers.payments.receipt.download', [$payment->customer, $payment]) }}">Descargar PDF</a>
            <a
                class="button button-whatsapp"
                href="{{ $whatsappUrl }}"
                target="_blank"
                rel="noopener"
                data-share-receipt
                data-download-url="{{ route('customers.payments.receipt.download', [$payment->customer, $payment]) }}"
                data-filename="{{ $filename }}"
                data-share-title="Comprobante de abono {{ $receipt['payment']['receipt_number'] }}"
                data-share-text="Comprobante de abono de {{ $receipt['company']['commercial_name'] }}"
            >Compartir por WhatsApp</a>
        </nav>
        @if(session('success'))
            <div class="notice no-print">{{ session('success') }}</div>
        @endif
    @endunless

    <main class="sheet">
        @if($isReversed)<div class="cancelled">ANULADO</div>@endif
        <div class="content">
            <table class="header">
                <tr>
                    <td style="width: 82px;">
                        @if($companyLogoDataUri)<img class="logo" src="{{ $companyLogoDataUri }}" alt="">@endif
                    </td>
                    <td>
                        <p class="brand">{{ $receipt['company']['commercial_name'] }}</p>
                        @if($receipt['company']['legal_name'])<p>{{ $receipt['company']['legal_name'] }}</p>@endif
                        @if($receipt['company']['rtn'])<p class="muted">RTN: {{ $receipt['company']['rtn'] }}</p>@endif
                        @if($receipt['company']['address'])<p class="muted">{{ $receipt['company']['address'] }}</p>@endif
                    </td>
                    <td class="right" style="width: 180px;">
                        @if($receipt['company']['phone'])<p>Tel. {{ $receipt['company']['phone'] }}</p>@endif
                        @if($receipt['company']['whatsapp'])<p>WhatsApp {{ $receipt['company']['whatsapp'] }}</p>@endif
                        @if($receipt['company']['email'])<p>{{ $receipt['company']['email'] }}</p>@endif
                        @if($receipt['company']['slogan'])<p class="muted">{{ $receipt['company']['slogan'] }}</p>@endif
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-top: 12px;"><tr>
                <td>
                    <h1 class="receipt-title">Comprobante de abono</h1>
                    <p class="receipt-number">{{ $receipt['payment']['receipt_number'] }}</p>
                    <span class="status {{ $isReversed ? 'status-reversed' : '' }}">{{ $isReversed ? 'ANULADO' : 'VÁLIDO' }}</span>
                </td>
                <td class="right">
                    <span class="label">Fecha y hora</span><span class="value">{{ $occurredAt->format('d/m/Y H:i') }}</span>
                    <span class="label" style="margin-top: 7px;">Código de control</span><span class="value">{{ mb_strtoupper(mb_substr($payment->id, -12)) }}</span>
                </td>
            </tr></table>

            <table class="grid">
                <tr>
                    <td><span class="label">Cliente</span><span class="value">{{ $receipt['customer']['business_name'] }}</span><span class="muted">{{ $receipt['customer']['code'] }}</span></td>
                    <td><span class="label">Contacto</span><span class="value">{{ $receipt['customer']['contact_name'] ?: 'No registrado' }}</span><span class="muted">{{ $receipt['customer']['phone'] ?: 'Sin teléfono' }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Dirección</span><span class="value">{{ $receipt['customer']['address'] ?: 'No registrada' }}</span></td>
                    <td><span class="label">Recibido por</span><span class="value">{{ $receipt['payment']['received_by'] }}</span></td>
                </tr>
            </table>

            <div class="amount-box"><span class="label">Total recibido</span><strong>{{ $money($receipt['payment']['amount']) }}</strong></div>

            <h2 class="section-title">Métodos de pago</h2>
            <table class="data">
                <thead><tr><th>Método</th><th>Referencia</th><th class="number">Monto</th></tr></thead>
                <tbody>
                    @forelse($receipt['payment']['methods'] as $method)
                        <tr><td><strong>{{ $method['label'] }}</strong></td><td>{{ $method['reference'] ?: '—' }}</td><td class="number">{{ $money($method['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="muted">El método no está disponible en este comprobante histórico.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2 class="section-title">Aplicación del abono</h2>
            <table class="data">
                <thead><tr><th>Documento</th><th>Origen</th><th class="number">Saldo anterior</th><th class="number">Abono</th><th class="number">Saldo restante</th></tr></thead>
                <tbody>
                    @foreach($receipt['allocations'] as $allocation)
                        <tr>
                            <td><strong>{{ $allocation['document_number'] ?: 'Deuda sin documento' }}</strong>@if($allocation['due_date'])<br><span class="muted">Vence {{ \Carbon\CarbonImmutable::parse($allocation['due_date'])->format('d/m/Y') }}</span>@endif</td>
                            <td>{{ $allocation['source_label'] }}</td>
                            <td class="number">{{ $allocation['balance_before'] !== null ? $money($allocation['balance_before']) : '—' }}</td>
                            <td class="number"><strong>{{ $money($allocation['amount']) }}</strong></td>
                            <td class="number">{{ $allocation['balance_after'] !== null ? $money($allocation['balance_after']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($receipt['balances']['before'] !== null)
                <table class="balance"><tr>
                    <td class="balance-before"><span class="label">Deuda total antes del abono</span><strong>{{ $money($receipt['balances']['before']) }}</strong></td>
                    <td class="balance-after"><span class="label">Saldo total pendiente</span><strong>{{ $money($receipt['balances']['after']) }}</strong></td>
                </tr></table>
            @endif

            @if($receipt['payment']['notes'])
                <div class="notes"><span class="label">Observaciones</span>{{ $receipt['payment']['notes'] }}</div>
            @endif

            @if($isReversed)
                <div class="reversal">
                    <strong>Este comprobante fue anulado el {{ $payment->reversed_at?->format('d/m/Y H:i') }}.</strong>
                    @if($payment->reversal?->notes)<p>Motivo: {{ $payment->reversal->notes }}</p>@endif
                    @if($payment->reversedBy)<p>Responsable: {{ $payment->reversedBy->name }}</p>@endif
                    @if($payment->reversal?->receipt_number)<p>Documento de reversión: {{ $payment->reversal->receipt_number }}</p>@endif
                </div>
            @endif

            @if($isReconstructed)
                <div class="legacy">Este comprobante corresponde a un abono anterior a la función de respaldo inmutable. Los métodos y aplicaciones se reconstruyeron desde el historial contable.</div>
            @endif

            <table class="signatures"><tr>
                <td><div class="signature-line">Recibido por</div></td>
                <td><div class="signature-line">Firma del cliente</div></td>
            </tr></table>

            <footer class="footer">Generado con Proximus · Comprobante interno no fiscal · Conserve este documento para futuras consultas.</footer>
        </div>
    </main>

    @unless($isPdf)
        <script>
            document.querySelector('[data-share-receipt]')?.addEventListener('click', async function (event) {
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

                button.textContent = 'Preparando comprobante…';

                try {
                    const response = await fetch(button.dataset.downloadUrl, { credentials: 'same-origin' });

                    if (!response.ok) {
                        throw new Error('No se pudo generar el comprobante.');
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
        </script>
    @endunless
</body>
</html>
