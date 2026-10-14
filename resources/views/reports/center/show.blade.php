@extends('layouts.app')

@use('App\Services\Reports\ReportFilters')
@use('App\Services\Reports\ReportValue')

@php
    $query = request()->query();
    $sortUrl = fn (string $key): string => route('reports.show', [
        'report' => $report->key(),
        ...request()->except(['page', 'sort', 'direction']),
        'sort' => $key,
        'direction' => $sortKey === $key && $sortDirection === 'desc' ? 'asc' : 'desc',
    ]);
@endphp

@section('title', $report->title())
@section('page-title', $report->title())
@section('page-subtitle', $report->usesPeriod() ? $filters->periodLabel().' · '.($report->dateLabel() ?? ReportFilters::BASES[$filters->basis]) : 'Saldos al '.today()->format('d/m/Y'))
@section('header-actions')
    <div class="flex flex-wrap gap-2">
        <a class="btn-secondary" target="_blank" rel="noopener" href="{{ route('reports.print', ['report' => $report->key(), ...$query]) }}">Imprimir</a>
        <a class="btn-secondary" href="{{ route('reports.download', ['report' => $report->key(), ...$query]) }}">PDF</a>
        @if($rows !== null)
            <a class="btn-secondary" href="{{ route('reports.export', ['report' => $report->key(), ...$query]) }}">Excel (CSV)</a>
        @endif
        <a class="btn-secondary" href="{{ route('reports.index') }}">Reportes</a>
    </div>
@endsection

@section('content')
    <div class="space-y-6">
        <form method="GET" class="card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4" @if($report->usesPeriod()) data-report-filters @endif>
            @if($report->usesPeriod())
            <div>
                <label class="form-label" for="period">Período</label>
                <select class="form-input" id="period" name="period" data-report-period>
                    @foreach(ReportFilters::PERIODS as $value => $label)
                        <option value="{{ $value }}" @selected($filters->period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="{{ $filters->period === 'custom' ? '' : 'hidden' }}" data-report-custom-range>
                <label class="form-label" for="from">Desde</label>
                <input class="form-input" id="from" name="from" type="date" value="{{ $filters->from->toDateString() }}">
            </div>
            <div class="{{ $filters->period === 'custom' ? '' : 'hidden' }}" data-report-custom-range>
                <label class="form-label" for="to">Hasta</label>
                <input class="form-input" id="to" name="to" type="date" value="{{ $filters->to->toDateString() }}">
            </div>
            @if($report->dateLabel() === null)
                <div>
                    <label class="form-label" for="basis">Fecha de</label>
                    <select class="form-input" id="basis" name="basis">
                        @foreach(ReportFilters::BASES as $value => $label)
                            <option value="{{ $value }}" @selected($filters->basis === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @endif
            @foreach($options as $dimension => $option)
                <div>
                    <label class="form-label" for="filter-{{ $dimension }}">{{ $option['label'] }}</label>
                    <select class="form-input" id="filter-{{ $dimension }}" name="{{ $dimension }}">
                        <option value="">Todos</option>
                        @foreach($option['items'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters->get($dimension) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            @if($report->searchPlaceholder())
                <div>
                    <label class="form-label" for="search">Buscar</label>
                    <input class="form-input" id="search" name="search" value="{{ $filters->search }}" placeholder="{{ $report->searchPlaceholder() }}">
                </div>
            @endif
            <div class="flex items-end gap-2">
                <button class="btn-primary flex-1" type="submit">Aplicar</button>
                <a class="btn-secondary" href="{{ route('reports.show', $report->key()) }}">Limpiar</a>
            </div>
        </form>

        @if($summary !== [])
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($summary as $indicator)
                    @php
                        $change = array_key_exists('previous', $indicator)
                            ? ReportValue::change($indicator['value'], $indicator['previous'], $indicator['format'])
                            : null;
                        $isGood = $change !== null && $change['direction'] !== 0
                            && ($change['direction'] > 0) !== ($indicator['lower_is_better'] ?? false);
                        $changeClass = match (true) {
                            $change === null || $change['direction'] === 0 => 'text-ink-600',
                            $isGood => 'text-emerald-700',
                            default => 'text-red-700',
                        };
                    @endphp
                    <article class="card p-5">
                        <p class="text-xs font-semibold tracking-wide text-ink-600 uppercase">{{ $indicator['label'] }}</p>
                        <p class="mt-2 text-2xl font-black text-ink-950">{{ ReportValue::format($indicator['value'], $indicator['format'], $currency) }}</p>
                        @if($change !== null)
                            <p class="mt-1 text-xs font-semibold {{ $changeClass }}">{{ $change['direction'] > 0 ? '▲' : ($change['direction'] < 0 ? '▼' : '') }} {{ $change['text'] }} <span class="font-normal text-ink-600">vs. período anterior</span></p>
                        @endif
                        @isset($indicator['hint'])
                            <p class="mt-1 text-xs text-ink-600">{{ $indicator['hint'] }}</p>
                        @endisset
                    </article>
                @endforeach
            </section>
        @endif

        @if($charts !== [])
            <section class="grid items-start gap-6 xl:grid-cols-2">
                @foreach($charts as $chart)
                    <x-report-chart :chart="$chart" :currency="$currency" />
                @endforeach
            </section>
        @endif

        @if($rows !== null)
            <section class="card overflow-hidden">
                <header class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <h2 class="font-bold text-ink-950">Detalle</h2>
                    <span class="text-sm text-ink-600">{{ number_format($rows->total()) }} {{ $rows->total() === 1 ? 'registro' : 'registros' }}</span>
                </header>
                @if($rows->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-ink-600">Sin registros con estos filtros.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-stone-50">
                                <tr>
                                    @foreach($columns as $column)
                                        <th class="table-heading whitespace-nowrap {{ ReportValue::isNumeric($column['format']) ? 'text-right' : '' }}">
                                            @if($column['sortable'] ?? false)
                                                <a class="hover:text-leaf-700" href="{{ $sortUrl($column['key']) }}">{{ $column['label'] }}@if($sortKey === $column['key']) {{ $sortDirection === 'desc' ? '↓' : '↑' }}@endif</a>
                                            @else
                                                {{ $column['label'] }}
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        @foreach($columns as $column)
                                            <td class="table-cell whitespace-nowrap {{ ReportValue::isNumeric($column['format']) ? 'text-right' : '' }}">
                                                @if(($row['_url'] ?? null) && $column['key'] === ($row['_link'] ?? $columns[0]['key']))
                                                    <a class="font-semibold text-leaf-700 hover:underline" href="{{ $row['_url'] }}">{{ ReportValue::format($row[$column['key']] ?? null, $column['format'], $currency) }}</a>
                                                @else
                                                    {{ ReportValue::format($row[$column['key']] ?? null, $column['format'], $currency) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            @if($totals !== [])
                                <tfoot class="border-t-2 border-stone-200 bg-stone-50">
                                    <tr>
                                        @foreach($columns as $column)
                                            <td class="table-cell whitespace-nowrap font-bold {{ ReportValue::isNumeric($column['format']) ? 'text-right' : '' }}">
                                                @if($loop->first)
                                                    Total
                                                @elseif(array_key_exists($column['key'], $totals))
                                                    {{ ReportValue::format($totals[$column['key']], $column['format'], $currency) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if($rows->hasPages())
                        <div class="border-t border-stone-100 px-5 py-4">{{ $rows->links() }}</div>
                    @endif
                @endif
            </section>
        @endif
    </div>
@endsection
