<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ruta {{ $salesRoute->code }} · {{ $deliveryRun->scheduled_date->format('d/m/Y') }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm; size: letter portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c241f; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; line-height: 1.35; }
        h1, h2, h3, p { margin: 0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #17251d; }
        .toolbar a, .toolbar button { border: 0; border-radius: 7px; padding: 9px 14px; font: 700 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #f5a524; color: #17251d; }
        .toolbar a { background: #fff; color: #17251d; }
        .page { max-width: 190mm; margin: 0 auto; }
        .header { width: 100%; border-bottom: 2px solid #2d6a4f; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        .logo { width: 70px; max-height: 56px; object-fit: contain; }
        .brand { font-size: 18px; font-weight: 800; color: #173e2b; }
        .muted { color: #5e6962; }
        .right { text-align: right; }
        .title { margin: 10px 0 3px; color: #173e2b; font-size: 17px; }
        .subtitle { font-size: 10px; color: #5e6962; }
        .meta, .metrics { width: 100%; border-collapse: separate; border-spacing: 4px; margin: 7px 0; }
        .meta td, .metric { border: 1px solid #d9e2dc; border-radius: 5px; padding: 6px; vertical-align: top; }
        .label { display: block; color: #667269; font-size: 7px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-size: 10px; font-weight: 700; }
        .metric { width: 20%; text-align: center; }
        .metric strong { display: block; font-size: 14px; color: #1f6b49; }
        .section-title { margin: 13px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #9eb5a6; color: #173e2b; font-size: 13px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #e9f2ec; color: #173e2b; font-size: 8px; text-align: left; }
        table.data th, table.data td { border: 1px solid #cbd8cf; padding: 5px; vertical-align: top; }
        table.data .number { text-align: right; white-space: nowrap; }
        .order { margin-top: 11px; border: 1px solid #aebeb3; border-radius: 6px; page-break-inside: avoid; }
        .order-header { padding: 7px; background: #f1f5f2; border-bottom: 1px solid #aebeb3; }
        .order-title { font-size: 11px; font-weight: 800; color: #173e2b; }
        .order-body { padding: 7px; }
        .customer-grid { width: 100%; margin-bottom: 6px; }
        .customer-grid td { width: 50%; padding: 2px 8px 2px 0; vertical-align: top; }
        .checks { margin-top: 7px; font-size: 9px; }
        .box { display: inline-block; width: 11px; height: 11px; margin: 0 3px 0 12px; border: 1px solid #53645a; vertical-align: -2px; }
        .box:first-child { margin-left: 0; }
        .notes-line { margin-top: 7px; padding-top: 8px; border-bottom: 1px solid #77847c; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #d1dad4; color: #68736c; font-size: 7px; text-align: center; }
        .empty { padding: 12px; border: 1px dashed #aebeb3; text-align: center; color: #68736c; }
        @media print {
            .no-print { display: none !important; }
            .page { max-width: none; }
        }
    </style>
</head>
<body>
    @php
        $quantity = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') ?: '0';
        $money = static fn ($value): string => 'L '.number_format((float) $value, 2);
    @endphp

    @unless ($isPdf)
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">Imprimir ahora</button>
            <a href="{{ route('route-reports.download', [$deliveryRun, $salesRoute]) }}">Descargar PDF</a>
        </div>
    @endunless

    <main class="page">
        <table class="header">
            <tr>
                <td style="width: 78px;">
                    @if ($companyLogoDataUri)
                        <img class="logo" src="{{ $companyLogoDataUri }}" alt="">
                    @endif
                </td>
                <td>
                    <p class="brand">{{ $companyProfile->commercial_name }}</p>
                    @if ($companyProfile->legal_name)<p>{{ $companyProfile->legal_name }}</p>@endif
                    @if ($companyProfile->rtn)<p class="muted">RTN: {{ $companyProfile->rtn }}</p>@endif
                    @if ($companyProfile->address)<p class="muted">{{ $companyProfile->address }}</p>@endif
                </td>
                <td class="right" style="width: 180px;">
                    @if ($companyProfile->phone)<p>Tel. {{ $companyProfile->phone }}</p>@endif
                    @if ($companyProfile->whatsapp)<p>WhatsApp {{ $companyProfile->whatsapp }}</p>@endif
                    @if ($companyProfile->email)<p>{{ $companyProfile->email }}</p>@endif
                    @if ($companyProfile->slogan)<p class="muted">{{ $companyProfile->slogan }}</p>@endif
                </td>
            </tr>
        </table>

        <h1 class="title">Consolidado diario de ruta</h1>
        <p class="subtitle">Lista para preparación de bodega, verificación de carga y seguimiento de reparto.</p>

        <table class="meta">
            <tr>
                <td><span class="label">Ruta</span><span class="value">{{ $salesRoute->code }} · {{ $salesRoute->name }}</span></td>
                <td><span class="label">Jornada</span><span class="value">{{ $deliveryRun->run_number }}</span></td>
                <td><span class="label">Fecha programada</span><span class="value">{{ $deliveryRun->scheduled_date->format('d/m/Y') }}</span></td>
                <td><span class="label">Estado</span><span class="value">{{ $deliveryRun->status->label() }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Preventista</span><span class="value">{{ $salesRoute->salesperson?->name ?? $runOrders->first()?->order?->salesperson_name ?? 'Sin asignar' }}</span></td>
                <td><span class="label">Repartidor</span><span class="value">{{ $deliveryRun->driver_name }}</span></td>
                <td><span class="label">Vehículo</span><span class="value">{{ $deliveryRun->vehicle_license_plate ?: ($deliveryRun->vehicle_code ?: 'Sin asignar') }}</span></td>
                <td><span class="label">Bodega</span><span class="value">{{ $deliveryRun->warehouse_name }}</span></td>
            </tr>
        </table>

        <table class="metrics">
            <tr>
                <td class="metric"><strong>{{ $metrics['orders'] }}</strong>Pedidos</td>
                <td class="metric"><strong>{{ $metrics['customers'] }}</strong>Clientes</td>
                <td class="metric"><strong>{{ $money($metrics['requested_total']) }}</strong>Total solicitado</td>
                <td class="metric"><strong>{{ $metrics['cash_orders'] }}</strong>Contado</td>
                <td class="metric"><strong>{{ $metrics['credit_orders'] }}</strong>Crédito</td>
            </tr>
        </table>

        <h2 class="section-title">1. Consolidado de productos</h2>
        @if ($consolidated->isEmpty())
            <div class="empty">Esta ruta no tiene productos para consolidar.</div>
        @else
            <table class="data">
                <thead>
                    <tr><th>SKU</th><th>Producto</th><th>Presentación</th><th class="number">Solicitado</th><th class="number">Equivalente base</th></tr>
                </thead>
                <tbody>
                    @foreach ($consolidated as $item)
                        <tr>
                            <td>{{ $item['product_sku'] }}</td>
                            <td><strong>{{ $item['product_name'] }}</strong></td>
                            <td>{{ $item['presentation_name'] }}</td>
                            <td class="number">{{ $quantity($item['requested_quantity']) }}</td>
                            <td class="number">{{ $quantity($item['requested_base_quantity']) }} {{ $item['base_unit_symbol'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2 class="section-title">2. Pedidos por cliente</h2>
        @forelse ($runOrders as $runOrder)
            @php($order = $runOrder->order)
            <section class="order">
                <div class="order-header">
                    <table style="width: 100%;"><tr>
                        <td><p class="order-title">Parada {{ $runOrder->visit_order }} · {{ $order->customer_name }}</p><p class="muted">{{ $order->customer_code }} · Pedido {{ $order->order_number }}</p></td>
                        <td class="right"><strong>{{ $money($runOrder->requested_total) }}</strong><br><span class="muted">{{ $order->payment_term->label() }}</span></td>
                    </tr></table>
                </div>
                <div class="order-body">
                    <table class="customer-grid">
                        <tr>
                            <td><span class="label">Dirección</span>{{ $order->customer_address ?: 'Sin dirección' }}</td>
                            <td><span class="label">Teléfono / WhatsApp</span>{{ $order->customer?->phone ?: '—' }} / {{ $order->customer?->whatsapp ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">Referencia</span>{{ $order->customer?->reference ?: '—' }}</td>
                            <td><span class="label">Notas del pedido</span>{{ $order->notes ?: '—' }}</td>
                        </tr>
                    </table>
                    <table class="data">
                        <thead><tr><th>SKU</th><th>Producto</th><th>Presentación</th><th class="number">Cantidad</th></tr></thead>
                        <tbody>
                            @foreach ($runOrder->items as $item)
                                <tr><td>{{ $item->product_sku }}</td><td>{{ $item->product_name }}</td><td>{{ $item->presentation_name }}</td><td class="number">{{ $quantity($item->requested_quantity) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="checks"><span class="box"></span> Preparado <span class="box"></span> Verificado <span class="box"></span> Entregado</p>
                    <p class="notes-line">Observaciones:</p>
                </div>
            </section>
        @empty
            <div class="empty">Esta ruta no tiene pedidos en la jornada.</div>
        @endforelse

        <footer class="footer">Generado con Proximus · {{ $generatedAt->format('d/m/Y H:i') }} · Documento interno no fiscal</footer>
    </main>
</body>
</html>
