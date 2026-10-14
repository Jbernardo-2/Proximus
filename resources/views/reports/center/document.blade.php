<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }} · {{ $filters->periodLabel() }}</title>
    @use('App\Services\Reports\ReportFilters')
    @use('App\Services\Reports\ReportValue')
    <style>
        @page { margin: 12mm 10mm 14mm; size: letter landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c241f; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8px; line-height: 1.35; }
        h1, h2, h3, p { margin: 0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #17251d; }
        .toolbar a, .toolbar button { border: 0; border-radius: 7px; padding: 9px 14px; font: 700 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #f5a524; color: #17251d; }
        .toolbar a { background: #fff; color: #17251d; }
        .page { max-width: 260mm; margin: 0 auto; padding: 8px; }
        .header { width: 100%; border-bottom: 2px solid #2d6a4f; padding-bottom: 8px; }
        .header td { vertical-align: top; }
        .logo { width: 64px; max-height: 50px; object-fit: contain; }
        .brand { font-size: 15px; font-weight: 800; color: #173e2b; }
        .muted { color: #5e6962; }
        .right { text-align: right; }
        .title { margin-top: 10px; color: #173e2b; font-size: 16px; }
        .indicators { width: 100%; border-collapse: separate; border-spacing: 4px; margin: 8px -4px 0; }
        .indicators td { width: 25%; border: 1px solid #d9e2dc; border-radius: 5px; padding: 6px; vertical-align: top; }
        .label { display: block; color: #667269; font-size: 7px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-size: 11px; font-weight: 800; }
        .section-title { margin: 12px 0 5px; padding-bottom: 3px; border-bottom: 1px solid #9eb5a6; color: #173e2b; font-size: 11px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #e9f2ec; color: #173e2b; font-size: 7px; text-align: left; }
        table.data th, table.data td { border: 1px solid #cbd8cf; padding: 4px; vertical-align: top; }
        table.data .number { text-align: right; white-space: nowrap; }
        table.data tfoot td { background: #f1f5f2; font-weight: 800; }
        .chart { width: 49%; display: inline-block; vertical-align: top; margin-right: 1%; }
        .footer { margin-top: 12px; padding-top: 6px; border-top: 1px solid #d3ddd6; color: #6c776f; font-size: 7px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            .page { max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    @unless($isPdf)
        <nav class="toolbar no-print" aria-label="Acciones del reporte">
            <a href="{{ route('reports.show', ['report' => $report->key(), ...request()->query()]) }}">Volver al reporte</a>
            <button type="button" onclick="window.print()">Imprimir</button>
            <a href="{{ route('reports.download', ['report' => $report->key(), ...request()->query()]) }}">Descargar PDF</a>
        </nav>
    @endunless

    <main class="page">
        <table class="header">
            <tr>
                <td style="width: 72px;">@if($companyLogoDataUri)<img class="logo" src="{{ $companyLogoDataUri }}" alt="">@endif</td>
                <td>
                    <p class="brand">{{ $company->commercial_name }}</p>
                    @if($company->legal_name)<p>{{ $company->legal_name }}</p>@endif
                    @if($company->rtn)<p class="muted">RTN: {{ $company->rtn }}</p>@endif
                </td>
                <td class="right" style="width: 260px;">
                    <p>Generado el {{ now()->format('d/m/Y H:i') }} por {{ $generatedBy }}</p>
                    <p class="muted">Moneda {{ $currency }} · Zona horaria {{ config('app.timezone') }}</p>
                </td>
            </tr>
        </table>

        <h1 class="title">{{ $report->title() }}</h1>
        @if($report->usesPeriod())
            <p>Período: <strong>{{ $filters->periodLabel() }}</strong> · {{ $report->dateLabel() ?? ReportFilters::BASES[$filters->basis] }}</p>
        @else
            <p>Saldos al <strong>{{ today()->format('d/m/Y') }}</strong></p>
        @endif
        <p class="muted">Filtros: {{ $appliedFilters->isEmpty() ? 'Ninguno' : $appliedFilters->implode(' · ') }}</p>

        @if($summary !== [])
            <table class="indicators">
                @foreach(array_chunk($summary, 4) as $indicatorRow)
                    <tr>
                        @foreach($indicatorRow as $indicator)
                            @php($change = array_key_exists('previous', $indicator) ? ReportValue::change($indicator['value'], $indicator['previous'], $indicator['format']) : null)
                            <td>
                                <span class="label">{{ $indicator['label'] }}</span>
                                <span class="value">{{ ReportValue::format($indicator['value'], $indicator['format'], $currency) }}</span>
                                @if($change !== null)<span class="muted">{{ $change['text'] }} vs. período anterior</span>@endif
                            </td>
                        @endforeach
                        @for($filler = count($indicatorRow); $filler < 4; $filler++)
                            <td style="border: 0;"></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach($charts as $chart)
            @continue($chart['labels'] === [])
            <div class="chart">
                <h2 class="section-title">{{ $chart['title'] }}</h2>
                <table class="data">
                    @if($chart['type'] === 'parts')
                        <tbody>
                            @foreach($chart['series'] as $item)
                                <tr><td>{{ $item['name'] }}</td><td class="number">{{ ReportValue::format($item['values'][0], $chart['format'], $currency) }}</td></tr>
                            @endforeach
                        </tbody>
                    @else
                        <thead><tr><th></th>@foreach($chart['series'] as $item)<th class="number">{{ $item['name'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($chart['labels'] as $index => $label)
                                <tr><td>{{ $label }}</td>@foreach($chart['series'] as $item)<td class="number">{{ ReportValue::format($item['values'][$index], $chart['format'], $currency) }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
        @endforeach

        @if($rows !== null)
            <h2 class="section-title">Detalle</h2>
            @if($rows->isEmpty())
                <p class="muted">Sin registros con estos filtros.</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th class="{{ ReportValue::isNumeric($column['format']) ? 'number' : '' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($columns as $column)
                                    <td class="{{ ReportValue::isNumeric($column['format']) ? 'number' : '' }}">{{ ReportValue::format($row[$column['key']] ?? null, $column['format'], $currency) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if($totals !== [])
                        <tfoot>
                            <tr>
                                @foreach($columns as $column)
                                    <td class="{{ ReportValue::isNumeric($column['format']) ? 'number' : '' }}">@if($loop->first)Total @elseif(array_key_exists($column['key'], $totals)){{ ReportValue::format($totals[$column['key']], $column['format'], $currency) }}@endif</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
                @if($isTruncated)
                    <p class="muted" style="margin-top: 4px;">Se muestran los primeros {{ number_format($rowLimit) }} registros; los totales cubren el reporte completo. Usa la exportación a Excel para el detalle íntegro.</p>
                @endif
            @endif
        @endif

        <p class="footer">Generado con Proximus · Documento interno</p>
    </main>
</body>
</html>
