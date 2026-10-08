@extends('layouts.app')

@section('title', 'Reparto')
@section('page-title', 'Despacho y reparto')
@section('page-subtitle', 'Preparación, mercancía en tránsito, entregas, cobros y liquidaciones.')
@section('header-actions')
    @can('manage-deliveries')<a href="{{ route('delivery-runs.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva jornada</span></a>@endcan
@endsection

@section('content')
    @php
        $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
        $quantity = static fn ($value): string => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
        $planningWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId);
    @endphp

    <div class="space-y-6">
        <nav class="flex flex-wrap gap-2 text-sm">
            <a class="btn-secondary border-leaf-600 text-leaf-700" href="{{ route('delivery-runs.index') }}">Jornadas</a>
            @can('manage-vehicles')<a class="btn-secondary" href="{{ route('vehicles.index') }}">Vehículos</a>@endcan
        </nav>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <a class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm transition hover:-translate-y-0.5" href="#pending-orders"><p class="text-sm font-bold text-red-800">Pedidos sin asignar</p><p class="mt-2 text-4xl font-black text-red-700">{{ number_format($metrics['pending_orders']) }}</p><p class="mt-1 text-xs text-red-800">{{ $currency }} {{ number_format((float) $metrics['pending_total'], 2) }} por despachar</p></a>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"><p class="text-sm font-bold text-amber-800">En preparación</p><p class="mt-2 text-4xl font-black text-amber-700">{{ number_format($metrics['preparing']) }}</p><p class="mt-1 text-xs text-amber-800">Bodega trabajando</p></article>
            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm"><p class="text-sm font-bold text-sky-800">Listas para salir</p><p class="mt-2 text-4xl font-black text-sky-700">{{ number_format($metrics['loaded']) }}</p><p class="mt-1 text-xs text-sky-800">Esperando iniciar ruta</p></article>
            <article class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm"><p class="text-sm font-bold text-indigo-800">En ruta</p><p class="mt-2 text-4xl font-black text-indigo-700">{{ number_format($metrics['in_transit']) }}</p><p class="mt-1 text-xs text-indigo-800">Mercancía en tránsito</p></article>
            <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5 shadow-sm"><p class="text-sm font-bold text-violet-800">Por liquidar</p><p class="mt-2 text-4xl font-black text-violet-700">{{ number_format($metrics['awaiting_settlement']) }}</p><p class="mt-1 text-xs text-violet-800">Cobros por revisar</p></article>
            <article class="card p-5"><p class="text-sm font-bold text-ink-600">Programadas hoy</p><p class="mt-2 text-4xl font-black text-ink-950">{{ number_format($metrics['today']) }}</p><p class="mt-1 text-xs text-ink-600">{{ number_format($metrics['open']) }} operaciones abiertas</p></article>
        </section>

        <section class="card overflow-hidden" id="pending-orders">
            <header class="border-b border-stone-100 bg-ink-950 px-5 py-5 text-white sm:px-7">
                <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold tracking-[0.14em] text-mint-100 uppercase">Plan de despacho</p><h2 class="mt-1 text-xl font-black">Pedidos pendientes y consolidado</h2><p class="mt-1 text-sm text-stone-300">Filtra la carga que quieres preparar; ambos paneles usan exactamente los mismos pedidos.</p></div><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">{{ $planningWarehouse?->name ?: 'Sin bodega' }}</span></div>
                <form method="GET" action="{{ route('delivery-runs.index') }}#pending-orders" class="mt-5 grid gap-3 md:grid-cols-4">
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="warehouse_id">Bodega</label><select class="form-input" id="warehouse_id" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="sales_route_id">Ruta</label><select class="form-input" id="sales_route_id" name="sales_route_id"><option value="">Todas las rutas</option>@foreach($salesRoutes as $route)<option value="{{ $route->id }}" @selected($selectedRouteId === $route->id)>{{ $route->name }} · {{ $route->code }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="planning_date">Entrega solicitada</label><input class="form-input" id="planning_date" name="planning_date" type="date" value="{{ $planningDate }}"></div>
                    <div class="flex items-end gap-2"><button class="btn-primary flex-1">Actualizar plan</button><a class="btn-secondary" href="{{ route('delivery-runs.index') }}#pending-orders">Limpiar</a></div>
                </form>
            </header>

            <div class="grid xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <section class="border-b border-stone-200 xl:border-r xl:border-b-0">
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h3 class="font-black text-ink-950">Pedidos sin jornada</h3><p class="text-xs text-ink-600">Confirmados y listos para asignar.</p></div><span class="rounded-full bg-red-100 px-3 py-1 text-sm font-black text-red-700">{{ $pendingOrders->total() }}</span></div>
                    @forelse($pendingOrders as $order)
                        <article class="border-b border-stone-100 p-5 last:border-0">
                            <div class="flex items-start justify-between gap-3"><div><a class="font-black text-leaf-700 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a><p class="text-base font-bold text-ink-950">{{ $order->customer_name }}</p></div><p class="text-right text-lg font-black text-ink-950">{{ $currency }} {{ number_format((float) $order->total, 2) }}</p></div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-stone-100 px-2.5 py-1 font-semibold text-ink-800">{{ $order->route_name ?: 'Fuera de ruta' }}</span><span class="rounded-full bg-stone-100 px-2.5 py-1 font-semibold text-ink-800">{{ $order->items_count }} líneas</span><span class="rounded-full {{ $order->requested_delivery_date?->isBefore(today()) ? 'bg-red-100 text-red-800' : 'bg-sky-100 text-sky-800' }} px-2.5 py-1 font-semibold">Entrega {{ $order->requested_delivery_date?->format('d/m/Y') ?: 'sin fecha' }}</span></div>
                        </article>
                    @empty
                        <div class="p-6"><x-empty-state title="Sin pedidos pendientes" description="No hay pedidos confirmados con estos filtros." /></div>
                    @endforelse
                    @if($pendingOrders->hasPages())<div class="border-t border-stone-100 p-4">{{ $pendingOrders->links() }}</div>@endif
                </section>

                <section>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h3 class="font-black text-ink-950">Consolidado por producto</h3><p class="text-xs text-ink-600">Suma de todos los pedidos visibles, con desglose por presentación.</p></div><span class="rounded-full bg-mint-100 px-3 py-1 text-sm font-black text-leaf-700">{{ $consolidated->total() }} productos</span></div>
                    @forelse($consolidated as $row)
                        @php
                            $product = $consolidatedProducts->get($row->product_id);
                            $stock = $planningStocks->get($row->product_id);
                            $hasShortage = bccomp((string) ($stock?->quantity_on_hand ?? '0'), (string) $row->total_base_quantity, 6) < 0;
                        @endphp
                        <article class="grid gap-4 border-b border-stone-100 p-5 last:border-0 sm:grid-cols-[4.5rem_minmax(0,1fr)_10rem]">
                            <div class="grid size-[4.5rem] place-items-center overflow-hidden rounded-xl border border-stone-200 bg-white">@if($product?->image_path)<img class="h-full w-full object-contain" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="">@else<span class="text-2xl font-black text-stone-300">{{ mb_substr($row->product_name, 0, 1) }}</span>@endif</div>
                            <div><div class="flex flex-wrap items-center gap-2"><h4 class="font-black text-ink-950">{{ $row->product_name }}</h4>@if($hasShortage)<span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-black text-red-700">Faltante físico</span>@endif</div><p class="text-xs text-ink-600">{{ $row->product_sku }}@if($product?->brand) · {{ $product->brand->name }}@endif @if($product?->category) · {{ $product->category->name }}@endif · {{ $row->orders_count }} pedidos</p><div class="mt-2 flex flex-wrap gap-1.5">@foreach($presentationTotals->get($row->product_id, collect()) as $presentation)<span class="rounded-lg bg-stone-100 px-2 py-1 text-xs text-ink-800"><strong>{{ $quantity($presentation->total_quantity) }}</strong> {{ $presentation->presentation_name }}</span>@endforeach</div></div>
                            <div class="sm:text-right"><p class="text-xs font-bold uppercase text-ink-600">Total solicitado</p><p class="text-2xl font-black {{ $hasShortage ? 'text-red-700' : 'text-leaf-700' }}">{{ $quantity($row->total_base_quantity) }}</p><p class="text-xs font-semibold text-ink-600">{{ $row->base_unit_symbol }} base</p><p class="mt-2 text-xs text-ink-600">Físico: <strong class="text-ink-950">{{ $quantity($stock?->quantity_on_hand ?? 0) }}</strong></p></div>
                        </article>
                    @empty
                        <div class="p-6"><x-empty-state title="Sin productos por consolidar" description="Los productos aparecerán al confirmar pedidos para estos filtros." /></div>
                    @endforelse
                    @if($consolidated->hasPages())<div class="border-t border-stone-100 p-4">{{ $consolidated->links() }}</div>@endif
                    <div class="border-t border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><strong>Control seguro:</strong> este consolidado sirve para planificar y preparar. El descuento real ocurre una sola vez al confirmar la carga de una jornada.</div>
                </section>
            </div>
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
