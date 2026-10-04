@extends('layouts.app')

@section('title', 'Reparto')
@section('page-title', 'Despacho y reparto')
@section('page-subtitle', 'Preparación, mercancía en tránsito, entregas, cobros y liquidaciones.')
@section('header-actions')
    @can('manage-deliveries')<a href="{{ route('delivery-runs.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva jornada</span></a>@endcan
@endsection

@section('content')
    @php($currency = mb_strtoupper((string) config('proximus.currency', 'HNL')))

    <div class="space-y-6">
        <nav class="flex flex-wrap gap-2 text-sm">
            <a class="btn-secondary border-leaf-600 text-leaf-700" href="{{ route('delivery-runs.index') }}">Jornadas</a>
            @can('manage-vehicles')<a class="btn-secondary" href="{{ route('vehicles.index') }}">Vehículos</a>@endcan
        </nav>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Programadas hoy</p><p class="mt-2 text-3xl font-black text-ink-950">{{ number_format($metrics['today']) }}</p><p class="mt-1 text-xs text-ink-600">Jornadas con fecha de hoy</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">En ruta</p><p class="mt-2 text-3xl font-black text-indigo-700">{{ number_format($metrics['in_transit']) }}</p><p class="mt-1 text-xs text-ink-600">Mercancía fuera de bodega</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Por liquidar</p><p class="mt-2 text-3xl font-black text-violet-700">{{ number_format($metrics['awaiting_settlement']) }}</p><p class="mt-1 text-xs text-ink-600">Paradas finalizadas</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Operaciones abiertas</p><p class="mt-2 text-3xl font-black text-amber-700">{{ number_format($metrics['open']) }}</p><p class="mt-1 text-xs text-ink-600">Sin liquidar ni cancelar</p></article>
        </section>

        <section class="card p-5">
            <form method="GET" action="{{ route('delivery-runs.index') }}" class="grid gap-4 lg:grid-cols-[minmax(14rem,1.4fr)_12rem_10rem_10rem_auto]">
                <div><label class="form-label" for="search">Buscar</label><input class="form-input" id="search" name="search" value="{{ $search }}" placeholder="Jornada, repartidor, cliente o pedido"></div>
                <div><label class="form-label" for="status">Estado</label><select class="form-input" id="status" name="status"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                <div><label class="form-label" for="date_from">Desde</label><input class="form-input" id="date_from" name="date_from" type="date" value="{{ $dateFrom }}"></div>
                <div><label class="form-label" for="date_to">Hasta</label><input class="form-input" id="date_to" name="date_to" type="date" value="{{ $dateTo }}"></div>
                <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filtrar</button><a class="btn-secondary" href="{{ route('delivery-runs.index') }}">Limpiar</a></div>
            </form>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-bold text-ink-950">Jornadas de reparto</h2><p class="text-sm text-ink-600">Cada jornada conserva su carga, resultados, cobros y cierre.</p></div>
            @if($deliveryRuns->isEmpty())
                <div class="p-6"><x-empty-state title="Sin jornadas" description="Crea una jornada y asigna pedidos confirmados para comenzar el despacho." /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px]">
                        <thead class="bg-stone-50"><tr><th class="table-heading">Jornada</th><th class="table-heading">Fecha</th><th class="table-heading">Repartidor y vehículo</th><th class="table-heading text-right">Pedidos</th><th class="table-heading text-right">Entregado</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acción</th></tr></thead>
                        <tbody>
                            @foreach($deliveryRuns as $run)
                                <tr>
                                    <td class="table-cell"><p class="font-bold text-ink-950">{{ $run->run_number }}</p><p class="text-xs text-ink-600">{{ $run->warehouse_name }}</p></td>
                                    <td class="table-cell"><p class="font-semibold text-ink-950">{{ $run->scheduled_date->format('d/m/Y') }}</p><p class="text-xs text-ink-600">Creada {{ $run->created_at->format('d/m/Y H:i') }}</p></td>
                                    <td class="table-cell"><p class="font-semibold text-ink-950">{{ $run->driver_name }}</p><p class="text-xs text-ink-600">{{ $run->vehicle_license_plate ?: ($run->vehicle_code ?: 'Sin vehículo asignado') }}</p></td>
                                    <td class="table-cell text-right font-bold text-ink-950">{{ $run->run_orders_count }}</td>
                                    <td class="table-cell text-right"><p class="font-black text-ink-950">{{ $currency }} {{ number_format((float) $run->delivered_total, 2) }}</p><p class="text-xs text-ink-600">Saldo {{ $currency }} {{ number_format((float) $run->credit_total, 2) }}</p></td>
                                    <td class="table-cell"><x-delivery-run-status-badge :status="$run->status" /></td>
                                    <td class="table-cell text-right"><a class="btn-secondary" href="{{ route('delivery-runs.show', $run) }}">Abrir</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-stone-100 px-5 py-4">{{ $deliveryRuns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
