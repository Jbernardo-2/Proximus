@props(['chart', 'currency'])

@php
    $format = fn ($value): string => \App\Services\Reports\ReportValue::format($value, $chart['format'], $currency);
    $series = $chart['series'];
    $labels = $chart['labels'];
    $allValues = collect($series)->flatMap(fn (array $item): array => $item['values']);
    $maximum = max((float) $allValues->max(), 0.0);
    $isEmpty = $labels === [] || (float) $allValues->sum() <= 0;
    $labelStep = max((int) ceil(count($labels) / 10), 1);
@endphp

<section {{ $attributes->merge(['class' => 'card p-5']) }} data-trend-chart data-default-readout="">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-bold text-ink-950">{{ $chart['title'] }}</h3>
        @if(count($series) > 1)
            <div class="flex flex-wrap items-center gap-4 text-xs text-ink-600">
                @foreach($series as $item)
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $item['color'] }}"></span>{{ $item['name'] }}</span>
                @endforeach
            </div>
        @endif
    </header>

    @if($isEmpty)
        <p class="py-10 text-center text-sm text-ink-600">Sin datos en el período.</p>
    @elseif($chart['type'] === 'columns')
        <p class="mt-2 h-4 text-xs text-ink-600" data-trend-readout aria-live="polite"></p>
        <div class="mt-3 grid h-44 gap-1 border-b border-stone-200" style="grid-template-columns: repeat({{ count($labels) }}, minmax(0, 1fr));" aria-hidden="true">
            @foreach($labels as $index => $label)
                <div class="flex h-full items-end justify-center gap-0.5 rounded-t-md transition hover:bg-stone-50" data-trend-day="{{ $label }}: {{ collect($series)->map(fn (array $item): string => mb_strtolower($item['name']).' '.$format($item['values'][$index]))->implode(' · ') }}">
                    @foreach($series as $item)
                        <div class="w-full max-w-5 rounded-t" style="height: {{ round(($item['values'][$index] / $maximum) * 100, 2) }}%; background: {{ $item['color'] }}"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="mt-2 grid gap-1 text-center text-[11px] text-ink-600" style="grid-template-columns: repeat({{ count($labels) }}, minmax(0, 1fr));" aria-hidden="true">
            @foreach($labels as $index => $label)
                <span class="truncate">{{ $index % $labelStep === 0 ? $label : '' }}</span>
            @endforeach
        </div>
        <table class="sr-only">
            <caption>{{ $chart['title'] }}</caption>
            <thead><tr><th>Período</th>@foreach($series as $item)<th>{{ $item['name'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($labels as $index => $label)
                    <tr><td>{{ $label }}</td>@foreach($series as $item)<td>{{ $format($item['values'][$index]) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @elseif($chart['type'] === 'bars')
        <dl class="mt-4 space-y-3">
            @foreach($labels as $index => $label)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-sm"><dt class="min-w-0 truncate text-ink-800">{{ $label }}</dt><dd class="shrink-0 font-semibold text-ink-950">{{ $format($series[0]['values'][$index]) }}</dd></div>
                    <div class="mt-1 h-2 rounded-full bg-stone-100" aria-hidden="true"><div class="h-full rounded-full" style="width: {{ round(($series[0]['values'][$index] / $maximum) * 100, 2) }}%; background: {{ $series[0]['color'] }}"></div></div>
                </div>
            @endforeach
        </dl>
    @else
        @php($total = (float) $allValues->sum())
        <div class="mt-5 flex h-3 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
            @foreach($series as $item)
                @if($item['values'][0] > 0)
                    <div style="width: {{ round(($item['values'][0] / $total) * 100, 2) }}%; background: {{ $item['color'] }}"></div>
                @endif
            @endforeach
        </div>
        <dl class="mt-4 space-y-2">
            @foreach($series as $item)
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <dt class="flex items-center gap-2 text-ink-800"><span class="size-2.5 rounded-sm" style="background: {{ $item['color'] }}"></span>{{ $item['name'] }}</dt>
                    <dd class="font-semibold text-ink-950">{{ $format($item['values'][0]) }} <span class="font-normal text-ink-600">· {{ number_format(($item['values'][0] / $total) * 100, 1) }} %</span></dd>
                </div>
            @endforeach
        </dl>
    @endif
</section>
