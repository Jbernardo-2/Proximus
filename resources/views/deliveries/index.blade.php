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

    @php
        $groups = [
            'Por preparar' => [\App\DeliveryRunStatus::Draft, \App\DeliveryRunStatus::Preparing],
            'Listas para salir' => [\App\DeliveryRunStatus::Loaded],
            'En ruta' => [\App\DeliveryRunStatus::InTransit],
            'Por liquidar' => [\App\DeliveryRunStatus::AwaitingSettlement],
        ];
        $nextStep = fn ($run): array => match ($run->status) {
            \App\DeliveryRunStatus::Draft => ['Asignar pedidos', ''],
            \App\DeliveryRunStatus::Preparing => ['Preparar carga', ''],
            \App\DeliveryRunStatus::Loaded => ['Iniciar ruta', ''],
            \App\DeliveryRunStatus::InTransit => ['Ver avance', ''],
            \App\DeliveryRunStatus::AwaitingSettlement => ['Liquidar', '#settlement'],
            default => ['Abrir', ''],
        };
        $planningOpen = request()->hasAny(['warehouse_id', 'sales_route_id', 'planning_date', 'orders_page', 'consolidated_page']);
        $cardColors = ['Por preparar' => 'border-amber-200 bg-amber-50 text-amber-800', 'En ruta' => 'border-indigo-200 bg-indigo-50 text-indigo-800', 'Por liquidar' => 'border-violet-200 bg-violet-50 text-violet-800'];
    @endphp

    <div class="space-y-6">
        <nav class="flex flex-wrap gap-2 text-sm">
            <a class="btn-secondary {{ $history ? '' : 'border-leaf-600 text-leaf-700' }}" href="{{ route('delivery-runs.index') }}">Jornadas activas</a>
            <a class="btn-secondary {{ $history ? 'border-leaf-600 text-leaf-700' : '' }}" href="{{ route('delivery-runs.index', ['historial' => 1]) }}">Historial</a>
            @can('manage-vehicles')<a class="btn-secondary" href="{{ route('vehicles.index') }}">Vehículos</a>@endcan
        </nav>

        @if($settledRun)
            {{-- A block, not inline php(): an inline one before a later php block breaks Blade. --}}
            @php
                $difference = (string) $settledRun->cash_difference;
                $balanced = bccomp($difference, '0', 4) === 0;
            @endphp
            <section class="rounded-2xl border {{ $balanced ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5" role="status">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-black text-ink-950">{{ $settledRun->run_number }} liquidada</p>
                        @if($balanced)
                            <p class="text-sm text-ink-800">Efectivo cuadrado: {{ $currency }} {{ number_format((float) $settledRun->cash_declared, 2) }}.</p>
                        @else
                            <p class="text-sm text-ink-800">Diferencia de efectivo: {{ $currency }} {{ number_format((float) $difference, 2) }} ({{ bccomp($difference, '0', 4) < 0 ? 'faltante' : 'sobrante' }}).</p>
                        @endif
                    </div>
                    <a class="btn-secondary" href="{{ route('delivery-runs.show', $settledRun) }}">Ver detalle</a>
                </div>
            </section>
        @endif

        @if($history)
            <section class="card p-5">
                <form method="GET" action="{{ route('delivery-runs.index') }}" class="grid gap-4 lg:grid-cols-[minmax(14rem,1.4fr)_12rem_10rem_10rem_auto]">
                    <input type="hidden" name="historial" value="1">
                    <div><label class="form-label" for="search">Buscar</label><input class="form-input" id="search" name="search" value="{{ $search }}" placeholder="Jornada, repartidor, cliente o pedido"></div>
                    <div><label class="form-label" for="status">Estado</label><select class="form-input" id="status" name="status"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                    <div><label class="form-label" for="date_from">Desde</label><input class="form-input" id="date_from" name="date_from" type="date" value="{{ $dateFrom }}"></div>
                    <div><label class="form-label" for="date_to">Hasta</label><input class="form-input" id="date_to" name="date_to" type="date" value="{{ $dateTo }}"></div>
                    <div class="flex items-end gap-2"><button class="btn-primary flex-1">Buscar</button><a class="btn-secondary" href="{{ route('delivery-runs.index', ['historial' => 1]) }}">Limpiar</a></div>
                </form>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-bold text-ink-950">Historial de jornadas</h2><p class="text-sm text-ink-600">Jornadas liquidadas y canceladas.</p></div>
                @if($deliveryRuns->isEmpty())
                    <div class="p-6"><x-empty-state title="Sin jornadas" description="No hay jornadas cerradas con estos filtros." /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px]">
                            <thead class="bg-stone-50"><tr><th class="table-heading">Jornada</th><th class="table-heading">Fecha</th><th class="table-heading">Repartidor</th><th class="table-heading text-right">Entregado</th><th class="table-heading text-right">Diferencia</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acción</th></tr></thead>
                            <tbody>
                                @foreach($deliveryRuns as $run)
                                    <tr>
                                        <td class="table-cell"><p class="font-bold text-ink-950">{{ $run->run_number }}</p><p class="text-xs text-ink-600">{{ $run->run_orders_count }} pedidos</p></td>
                                        <td class="table-cell font-semibold text-ink-950">{{ $run->scheduled_date->format('d/m/Y') }}</td>
                                        <td class="table-cell font-semibold text-ink-950">{{ $run->driver_name }}</td>
                                        <td class="table-cell text-right"><p class="font-black text-ink-950">{{ $currency }} {{ number_format((float) $run->delivered_total, 2) }}</p><p class="text-xs text-ink-600">Crédito {{ $currency }} {{ number_format((float) $run->credit_total, 2) }}</p></td>
                                        <td class="table-cell text-right font-bold {{ bccomp((string) $run->cash_difference, '0', 4) === 0 ? 'text-ink-600' : 'text-red-700' }}">{{ $run->status === \App\DeliveryRunStatus::Settled ? $currency.' '.number_format((float) $run->cash_difference, 2) : '—' }}</td>
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
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @can('manage-deliveries')
                    <a class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm transition hover:-translate-y-0.5" href="{{ route('delivery-runs.index', ['warehouse_id' => $selectedWarehouseId]) }}#pending-orders"><p class="text-sm font-bold text-red-800">Pedidos sin asignar</p><p class="mt-2 text-4xl font-black text-red-700">{{ number_format($metrics['pending_orders']) }}</p><p class="mt-1 text-xs text-red-800">{{ $currency }} {{ number_format((float) $metrics['pending_total'], 2) }} por despachar</p></a>
                @endcan
                @foreach($cardColors as $label => $classes)
                    <a class="rounded-2xl border p-5 shadow-sm {{ $classes }}" href="#{{ \Illuminate\Support\Str::slug($label) }}"><p class="text-sm font-bold">{{ $label }}</p><p class="mt-2 text-4xl font-black">{{ number_format($deliveryRuns->filter(fn ($run) => in_array($run->status, $groups[$label], true))->count()) }}</p></a>
                @endforeach
            </section>

            @can('manage-deliveries')
                <details class="card overflow-hidden group" id="pending-orders" @if($planningOpen) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 bg-ink-950 px-5 py-4 text-white sm:px-7">
                        <span><span class="block text-xs font-bold tracking-[0.14em] text-mint-100 uppercase">Plan de despacho</span><span class="text-lg font-black">Pedidos pendientes ({{ $metrics['pending_orders'] }}) y consolidado</span></span>
                        <span class="flex items-center gap-2 text-sm font-bold">{{ $planningWarehouse?->name ?: 'Sin bodega' }}<span class="transition group-open:rotate-180">⌄</span></span>
                    </summary>
                    <div class="border-b border-stone-100 bg-ink-950 px-5 pb-5 sm:px-7">
                <form method="GET" action="{{ route('delivery-runs.index') }}#pending-orders" class="grid gap-3 md:grid-cols-4">
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="warehouse_id">Bodega</label><select class="form-input" id="warehouse_id" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="sales_route_id">Ruta</label><select class="form-input" id="sales_route_id" name="sales_route_id"><option value="">Todas las rutas</option>@foreach($salesRoutes as $route)<option value="{{ $route->id }}" @selected($selectedRouteId === $route->id)>{{ $route->name }} · {{ $route->code }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-bold text-stone-200" for="planning_date">Entrega solicitada</label><input class="form-input" id="planning_date" name="planning_date" type="date" value="{{ $planningDate }}"></div>
                    <div class="flex items-end gap-2"><button class="btn-primary flex-1">Actualizar plan</button><a class="btn-secondary" href="{{ route('delivery-runs.index') }}#pending-orders">Limpiar</a></div>
                </form>
            </div>

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
                </details>
            @endcan

            @if($deliveryRuns->isEmpty())
                <div class="card p-6"><x-empty-state title="Sin jornadas activas" description="Crea una jornada y asigna pedidos confirmados para comenzar el despacho." /></div>
            @endif
            @foreach($groups as $label => $groupStatuses)
                @php($groupRuns = $deliveryRuns->filter(fn ($run) => in_array($run->status, $groupStatuses, true)))
                @if($groupRuns->isNotEmpty())
                    <section class="space-y-3" id="{{ \Illuminate\Support\Str::slug($label) }}">
                        <h2 class="text-lg font-black text-ink-950">{{ $label }} <span class="text-ink-600">({{ $groupRuns->count() }})</span></h2>
                        @foreach($groupRuns as $run)
                            @php([$stepLabel, $stepAnchor] = $nextStep($run))
                            @php($stale = $run->status === \App\DeliveryRunStatus::InTransit && $run->departed_at?->lt(now()->subDay()))
                            <article class="card flex flex-wrap items-center justify-between gap-4 p-4 {{ $stale ? 'border-amber-300' : '' }}">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><p class="font-black text-ink-950">{{ $run->run_number }}</p><x-delivery-run-status-badge :status="$run->status" /></div>
                                    <p class="text-sm text-ink-600">{{ $run->scheduled_date->format('d/m/Y') }} · {{ $run->driver_name }} · {{ $run->vehicle_license_plate ?: ($run->vehicle_code ?: 'Sin vehículo') }} · {{ $run->run_orders_count }} pedidos</p>
                                    @if($stale)<p class="mt-1 text-sm font-bold text-amber-800">En ruta desde {{ $run->departed_at->format('d/m H:i') }} ({{ $run->departed_at->diffForHumans() }}). Revisa si ya debió cerrar.</p>@endif
                                </div>
                                <div class="flex items-center gap-4">
                                    @if(bccomp((string) $run->delivered_total, '0', 4) > 0)<p class="text-right"><span class="block font-black text-ink-950">{{ $currency }} {{ number_format((float) $run->delivered_total, 2) }}</span><span class="text-xs text-ink-600">entregado</span></p>@endif
                                    <a class="{{ $run->status === \App\DeliveryRunStatus::AwaitingSettlement ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('delivery-runs.show', $run) }}{{ $stepAnchor }}">{{ $stepLabel }}</a>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endif
            @endforeach
        @endif
    </div>
@endsection
