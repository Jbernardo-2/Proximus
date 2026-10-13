@extends('layouts.app')

@section('title', 'Resumen')
@section('page-title', 'Resumen de la operación')
@section('page-subtitle', match (true) {
    $driverDashboard !== null => 'Tu ruta, entregas y cobranza de hoy.',
    $managementDashboard !== null => ucfirst(now()->translatedFormat('l j \d\e F')),
    default => 'Prioridades, avances y novedades según tu perfil.',
})

@section('header-actions')
    @can('manage-orders')
        <a href="{{ route('orders.create') }}" class="btn-primary">+ <span class="hidden sm:inline">Nuevo pedido</span></a>
    @elsecan('operate-inventory')
        <a href="{{ route('inventory-documents.create') }}" class="btn-primary">+ <span class="hidden sm:inline">Movimiento</span></a>
    @endcan
@endsection

@section('content')
    @if($driverDashboard !== null)
        @php
            $driverMetrics = $driverDashboard['metrics'];
            $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
            $currentRun = $driverDashboard['currentRun'];
            $nextStop = $driverDashboard['nextStop'];
        @endphp

        <div class="space-y-7" data-driver-dashboard>
            <section class="overflow-hidden rounded-3xl bg-ink-950 text-white shadow-xl">
                <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-center">
                    <div>
                        <p class="text-xs font-bold tracking-[0.18em] text-mint-100 uppercase">Panel del repartidor</p>
                        <h2 class="mt-2 text-3xl font-black sm:text-4xl">{{ $currentRun ? 'Tu jornada está en marcha' : 'No tienes jornadas activas' }}</h2>
                        <p class="mt-2 text-stone-300">{{ $currentRun ? $currentRun->run_number.' · '.$currentRun->scheduled_date->format('d/m/Y').' · '.($currentRun->vehicle_license_plate ?: 'Sin vehículo') : 'Cuando bodega cargue una jornada, aquí aparecerán tus paradas y el progreso.' }}</p>
                        @if($currentRun)
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a class="btn-primary" href="{{ route('delivery-runs.show', $currentRun) }}#delivery-progress">Continuar jornada</a>
                                <a class="btn-secondary border-white/20 bg-white/10 text-white hover:bg-white/20" href="{{ route('delivery-runs.index') }}">Ver mis jornadas</a>
                            </div>
                        @endif
                    </div>
                    <div class="relative mx-auto grid size-48 place-items-center">
                        <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 120 120" role="img" aria-label="{{ $driverMetrics['progress'] }}% completado">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="10" class="text-white/10" />
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="10" pathLength="100" stroke-dasharray="{{ $driverMetrics['progress'] }} 100" stroke-linecap="round" class="text-mint-100" />
                        </svg>
                        <div class="text-center"><p class="text-4xl font-black">{{ $driverMetrics['progress'] }}%</p><p class="text-xs font-bold text-stone-300 uppercase">completado</p></div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Paradas</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $driverMetrics['completed'] }}/{{ $driverMetrics['stops'] }}</p><p class="mt-1 text-xs text-ink-600">{{ $driverMetrics['pending'] }} pendientes</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Venta entregada</p><p class="mt-2 text-3xl font-black text-ink-950">{{ $currency }} {{ number_format((float) $driverMetrics['delivered'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Mercancía recibida</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Cobrado</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $currency }} {{ number_format((float) $driverMetrics['collected'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Pagos registrados</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Crédito</p><p class="mt-2 text-3xl font-black text-amber-700">{{ $currency }} {{ number_format((float) $driverMetrics['credit'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Saldo por cobrar</p></article>
            </section>

            @if($nextStop)
                <section class="grid gap-5 rounded-2xl border border-sky-200 bg-sky-50 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div><p class="text-xs font-bold tracking-[0.14em] text-sky-700 uppercase">Siguiente parada · #{{ $nextStop->visit_order }}</p><h2 class="mt-1 text-2xl font-black text-ink-950">{{ $nextStop->order->customer_name }}</h2><p class="mt-1 text-sm text-ink-700">{{ $nextStop->order->order_number }} · {{ $nextStop->order->customer_address ?: 'Dirección no registrada' }}</p></div>
                    <a class="btn-primary" href="{{ route('delivery-runs.show', $currentRun) }}#stop-{{ $nextStop->id }}">Abrir entrega</a>
                </section>
            @endif

            <section>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div><p class="text-xs font-bold tracking-[0.14em] text-leaf-700 uppercase">Jornadas visibles</p><h2 class="mt-1 text-xl font-black text-ink-950">Tu operación</h2></div>
                    <div class="flex gap-2" role="group" aria-label="Filtrar jornadas">
                        <button class="btn-secondary bg-ink-950 text-white" type="button" data-run-filter="all" aria-pressed="true">Todas</button>
                        <button class="btn-secondary" type="button" data-run-filter="open" aria-pressed="false">Abiertas</button>
                        <button class="btn-secondary" type="button" data-run-filter="completed" aria-pressed="false">Finalizadas</button>
                    </div>
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @forelse($driverDashboard['runs'] as $run)
                        @php
                            $runProgress = $run->stops_count > 0 ? (int) round(($run->completed_stops_count / $run->stops_count) * 100) : 0;
                            $runFilter = $run->status === \App\DeliveryRunStatus::Settled ? 'completed' : 'open';
                        @endphp
                        <a class="card block p-5 transition hover:-translate-y-0.5 hover:border-leaf-300 hover:shadow-md" href="{{ route('delivery-runs.show', $run) }}" data-driver-run="{{ $runFilter }}">
                            <div class="flex items-start justify-between gap-4"><div><p class="font-black text-ink-950">{{ $run->run_number }}</p><p class="text-sm text-ink-600">{{ $run->scheduled_date->format('d/m/Y') }} · {{ $run->vehicle_license_plate ?: 'Sin placa' }}</p></div><x-delivery-run-status-badge :status="$run->status" /></div>
                            <div class="mt-4 flex items-center justify-between text-sm"><span class="font-semibold text-ink-700">{{ $run->completed_stops_count }} de {{ $run->stops_count }} paradas</span><span class="font-black text-leaf-700">{{ $runProgress }}%</span></div>
                            <progress class="mt-2 h-2 w-full accent-leaf-600" value="{{ $run->completed_stops_count }}" max="{{ max($run->stops_count, 1) }}">{{ $runProgress }}%</progress>
                        </a>
                    @empty
                        <div class="lg:col-span-2"><x-empty-state title="Sin jornadas asignadas" description="Todavía no hay jornadas abiertas para tu usuario." /></div>
                    @endforelse
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($driverDashboard['achievements'] as $achievement)
                    <article class="rounded-2xl border p-5 {{ $achievement['earned'] ? 'border-violet-200 bg-violet-50' : 'border-stone-200 bg-stone-50 opacity-60' }}"><div class="grid size-10 place-items-center rounded-full {{ $achievement['earned'] ? 'bg-violet-600 text-white' : 'bg-stone-200 text-stone-500' }} font-black">{{ $achievement['earned'] ? '✓' : '○' }}</div><h3 class="mt-3 font-black text-ink-950">{{ $achievement['label'] }}</h3><p class="mt-1 text-sm text-ink-600">{{ $achievement['description'] }}</p></article>
                @endforeach
            </section>
        </div>
    @elseif($managementDashboard !== null)
        @include('dashboards.partials.management', ['dashboard' => $managementDashboard])
    @else
        @php
            $toneClasses = [
                'emerald' => 'bg-emerald-500 text-emerald-700 border-emerald-200',
                'sky' => 'bg-sky-500 text-sky-700 border-sky-200',
                'amber' => 'bg-amber-500 text-amber-800 border-amber-200',
                'red' => 'bg-red-500 text-red-700 border-red-200',
                'violet' => 'bg-violet-500 text-violet-700 border-violet-200',
            ];
            $badgeClasses = [
                'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
                'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                'red' => 'border-red-200 bg-red-50 text-red-700',
                'violet' => 'border-violet-200 bg-violet-50 text-violet-700',
            ];
        @endphp

        <div class="space-y-7" data-role-dashboard>
            <section class="overflow-hidden rounded-3xl bg-ink-950 text-white shadow-xl">
                <div class="relative p-6 sm:p-8">
                    <div class="absolute -right-20 -top-24 size-72 rounded-full bg-leaf-500/20 blur-3xl"></div>
                    <div class="relative max-w-3xl">
                        <p class="text-xs font-bold tracking-[0.18em] text-mint-100 uppercase">{{ $roleDashboard['eyebrow'] }}</p>
                        <h2 class="mt-2 text-3xl font-black sm:text-4xl">{{ $roleDashboard['title'] }}</h2>
                        <p class="mt-3 max-w-2xl text-sm text-stone-300 sm:text-base">{{ $roleDashboard['description'] }}</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($roleDashboard['cards'] as $card)
                    @php($tone = $toneClasses[$card['tone']] ?? $toneClasses['emerald'])
                    <a href="{{ $card['url'] }}" class="card group relative overflow-hidden p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="absolute right-4 top-4 size-2.5 rounded-full {{ str($tone)->before(' text-') }}"></span>
                        <p class="text-sm font-semibold text-ink-600">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-black tracking-tight text-ink-950">{{ ($card['format'] ?? null) === 'money' ? 'HNL '.number_format((float) $card['value'], 2) : number_format((int) $card['value']) }}</p>
                        <p class="mt-1 text-xs text-ink-600">{{ $card['hint'] }}</p>
                        <p class="mt-3 text-xs font-bold text-leaf-700 opacity-0 transition group-hover:opacity-100">Abrir detalle →</p>
                    </a>
                @endforeach
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,.65fr)]">
                <div class="card p-5 sm:p-6" data-dashboard-chart data-orders="{{ implode(',', $roleDashboard['trend']['orders']) }}" data-sales="{{ implode(',', $roleDashboard['trend']['sales']) }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="text-xs font-bold tracking-[0.14em] text-leaf-700 uppercase">Tendencia de 7 días</p><h2 class="mt-1 text-xl font-black text-ink-950">Actividad de pedidos</h2></div>
                        <div class="flex gap-2"><button type="button" class="btn-secondary bg-ink-950 text-white" data-chart-mode="orders" aria-pressed="true">Pedidos</button><button type="button" class="btn-secondary" data-chart-mode="sales" aria-pressed="false">Venta</button></div>
                    </div>
                    <div class="mt-6 grid h-56 grid-cols-7 items-end gap-2 sm:gap-4" data-chart-bars>
                        @php($maxOrders = max(max($roleDashboard['trend']['orders']), 1))
                        @foreach($roleDashboard['trend']['labels'] as $index => $label)
                            <div class="flex h-full flex-col justify-end gap-2 text-center">
                                <span class="text-xs font-bold text-ink-700" data-chart-value>{{ $roleDashboard['trend']['orders'][$index] }}</span>
                                <div class="mx-auto w-full max-w-12 rounded-t-lg bg-leaf-500 transition-all duration-300" data-chart-bar style="height: {{ max(5, ($roleDashboard['trend']['orders'][$index] / $maxOrders) * 100) }}%"></div>
                                <span class="text-[10px] font-semibold text-ink-600 sm:text-xs">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-leaf-200 bg-mint-50 p-5 sm:p-6">
                    <p class="text-xs font-bold tracking-[0.14em] text-leaf-700 uppercase">Enfoque</p>
                    <h2 class="mt-1 text-xl font-black text-ink-950">Filtra tu cola</h2>
                    <p class="mt-2 text-sm text-ink-600">Muestra todo el trabajo o concéntrate en lo que requiere atención inmediata.</p>
                    <div class="mt-5 grid gap-2">
                        <button type="button" class="btn-primary justify-center" data-work-filter="all" aria-pressed="true">Todo el trabajo</button>
                        <button type="button" class="btn-secondary justify-center" data-work-filter="urgent" aria-pressed="false">Sólo prioridades</button>
                        <button type="button" class="btn-secondary justify-center" data-work-filter="routine" aria-pressed="false">Seguimiento normal</button>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                @foreach($roleDashboard['sections'] as $section)
                    <article class="card overflow-hidden" data-work-section>
                        <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-black text-ink-950">{{ $section['title'] }}</h2><p class="text-sm text-ink-600">{{ $section['description'] }}</p></div>
                        <div class="divide-y divide-stone-100" data-work-list>
                            @forelse($section['items'] as $item)
                                @php($itemTone = $badgeClasses[$item['tone']] ?? $badgeClasses['sky'])
                                <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60" data-work-item="{{ $item['priority'] }}">
                                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="truncate font-bold text-ink-950">{{ $item['title'] }}</p><span class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase {{ $itemTone }}">{{ $item['badge'] }}</span></div><p class="mt-1 text-sm text-ink-600">{{ $item['meta'] }}</p></div>
                                    <span class="shrink-0 text-xs font-bold text-leaf-700">{{ $item['action'] }} →</span>
                                </a>
                            @empty
                                <div class="p-6" data-work-empty><x-empty-state title="Todo al día" :description="$section['empty']" /></div>
                            @endforelse
                        </div>
                        <div class="hidden p-6 text-center text-sm text-ink-600" data-filter-empty>No hay elementos con este filtro.</div>
                    </article>
                @endforeach
            </section>
        </div>
    @endif
@endsection
