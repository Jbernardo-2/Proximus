@extends('layouts.app')

@section('title', 'Resumen')
@section('page-title', 'Resumen de la operación')
@section('page-subtitle', auth()->user()->role === \App\UserRole::Repartidor ? 'Tu ruta, entregas y cobranza de hoy en un solo lugar.' : 'Clientes, rutas, preventa, inventario y catálogo en una sola operación.')

@section('header-actions')
    @can('manage-orders')
        <a href="{{ route('orders.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo pedido</span></a>
    @elsecan('operate-inventory')
        <a href="{{ route('inventory-documents.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Movimiento</span></a>
    @elsecan('manage-customers')
        <a href="{{ route('customers.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo cliente</span></a>
    @elsecan('manage-catalog')
        <a href="{{ route('products.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo producto</span></a>
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
                        @if($currentRun)
                            <p class="mt-2 text-stone-300">{{ $currentRun->run_number }} · {{ $currentRun->scheduled_date->format('d/m/Y') }} · {{ $currentRun->vehicle_license_plate ?: ($currentRun->vehicle_code ?: 'Sin vehículo') }}</p>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a class="btn-primary" href="{{ route('delivery-runs.show', $currentRun) }}#delivery-progress">Continuar jornada</a>
                                <a class="btn-secondary border-white/20 bg-white/10 text-white hover:bg-white/20" href="{{ route('delivery-runs.index') }}">Ver mis jornadas</a>
                            </div>
                        @else
                            <p class="mt-2 text-stone-300">Cuando bodega cargue una jornada, aquí aparecerán tus paradas y el progreso.</p>
                        @endif
                    </div>
                    <div class="relative mx-auto grid size-48 place-items-center">
                        <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 120 120" role="img" aria-label="{{ $driverMetrics['progress'] }}% de la ruta completada">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="10" class="text-white/10" />
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="10" pathLength="100" stroke-dasharray="{{ $driverMetrics['progress'] }} 100" stroke-linecap="round" class="text-mint-100 transition-all duration-500" />
                        </svg>
                        <div class="text-center"><p class="text-4xl font-black">{{ $driverMetrics['progress'] }}%</p><p class="text-xs font-bold text-stone-300 uppercase">completado</p></div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Paradas completadas</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $driverMetrics['completed'] }}/{{ $driverMetrics['stops'] }}</p><p class="mt-1 text-xs text-ink-600">{{ $driverMetrics['pending'] }} pendientes</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Venta entregada</p><p class="mt-2 text-3xl font-black text-ink-950">{{ $currency }} {{ number_format((float) $driverMetrics['delivered'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Mercancía recibida por clientes</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Cobrado</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $currency }} {{ number_format((float) $driverMetrics['collected'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Efectivo y pagos electrónicos</p></article>
                <article class="card p-5"><p class="text-xs font-bold text-ink-600 uppercase">Saldo a crédito</p><p class="mt-2 text-3xl font-black {{ bccomp($driverMetrics['credit'], '0', 4) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $currency }} {{ number_format((float) $driverMetrics['credit'], 2) }}</p><p class="mt-1 text-xs text-ink-600">Pendiente de cobranza</p></article>
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
                            <progress class="mt-2 h-2 w-full overflow-hidden rounded-full accent-leaf-600" value="{{ $run->completed_stops_count }}" max="{{ max($run->stops_count, 1) }}">{{ $runProgress }}%</progress>
                        </a>
                    @empty
                        <div class="lg:col-span-2"><x-empty-state title="Sin jornadas asignadas" description="Todavía no hay jornadas abiertas o liquidadas hoy para tu usuario." /></div>
                    @endforelse
                </div>
            </section>

            <section>
                <div><p class="text-xs font-bold tracking-[0.14em] text-violet-700 uppercase">Logros de la jornada</p><h2 class="mt-1 text-xl font-black text-ink-950">Tu progreso</h2></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($driverDashboard['achievements'] as $achievement)
                        <article class="rounded-2xl border p-5 {{ $achievement['earned'] ? 'border-violet-200 bg-violet-50' : 'border-stone-200 bg-stone-50 opacity-60' }}"><div class="grid size-10 place-items-center rounded-full {{ $achievement['earned'] ? 'bg-violet-600 text-white' : 'bg-stone-200 text-stone-500' }} text-lg font-black">{{ $achievement['earned'] ? '✓' : '○' }}</div><h3 class="mt-3 font-black text-ink-950">{{ $achievement['label'] }}</h3><p class="mt-1 text-sm text-ink-600">{{ $achievement['description'] }}</p></article>
                    @endforeach
                </div>
            </section>
        </div>
    @else
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [];

            if (auth()->user()->canManageCustomers()) {
                $cards[] = ['label' => 'Clientes', 'value' => $metrics['customers'], 'hint' => $metrics['active_customers'].' activos', 'accent' => 'bg-leaf-700'];
            }

            if (auth()->user()->canViewRoutes()) {
                $cards[] = ['label' => 'Rutas', 'value' => $metrics['routes'], 'hint' => $metrics['active_routes'].' activas', 'accent' => 'bg-amber-500'];
            }

            if (auth()->user()->canViewOrders()) {
                $cards[] = ['label' => 'Pedidos', 'value' => $metrics['orders'], 'hint' => $metrics['draft_orders'].' borradores · '.$metrics['confirmed_orders'].' confirmados', 'accent' => 'bg-emerald-600'];
            }

            if (auth()->user()->canViewInventory()) {
                $cards[] = ['label' => 'Inventario', 'value' => $metrics['inventory_products'], 'hint' => $metrics['inventory_shortages'].' faltantes · '.$metrics['inventory_low'].' por reponer', 'accent' => 'bg-teal-600'];
            }

            if (auth()->user()->canManageCatalog()) {
                $cards[] = ['label' => 'Productos', 'value' => $metrics['products'], 'hint' => $metrics['active_products'].' activos', 'accent' => 'bg-sky-600'];
                $cards[] = ['label' => 'Proveedores', 'value' => $metrics['suppliers'], 'hint' => $metrics['categories'].' categorías · '.$metrics['brands'].' marcas', 'accent' => 'bg-violet-600'];
            }
        @endphp

        @foreach ($cards as $card)
            <article class="card relative overflow-hidden p-5">
                <span class="absolute right-4 top-4 size-2.5 rounded-full {{ $card['accent'] }}"></span>
                <p class="text-sm font-semibold text-ink-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-black tracking-tight text-ink-950">{{ number_format($card['value']) }}</p>
                <p class="mt-1 text-xs text-ink-600">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    @can('view-orders')
        <section class="mt-7 card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4">
                <div><h2 class="font-bold text-ink-950">Pedidos recientes</h2><p class="text-sm text-ink-600">Actividad visible según tu rol y rutas asignadas</p></div>
                <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-leaf-700 hover:underline">Ver todos</a>
            </div>
            @if ($recentOrders->isEmpty())
                <div class="p-6"><x-empty-state title="Aún no hay pedidos" description="Crea el primer borrador desde una visita de ruta." /></div>
            @else
                <div class="grid divide-y divide-stone-100 md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-3">
                    @foreach ($recentOrders as $order)
                        <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60"><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ $order->order_number }} · {{ $order->customer_name }}</p><p class="truncate text-sm text-ink-600">{{ $order->status->label() }} · {{ $order->salesperson_name }}</p></div><p class="shrink-0 font-black text-ink-950">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p></a>
                    @endforeach
                </div>
            @endif
        </section>
    @endcan

    <section class="mt-7 grid gap-6 {{ auth()->user()->canManageCustomers() && auth()->user()->canManageCatalog() ? 'xl:grid-cols-2' : '' }}">
        @can('manage-customers')
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4">
                    <div><h2 class="font-bold text-ink-950">Clientes recientes</h2><p class="text-sm text-ink-600">Últimos negocios registrados</p></div>
                    <a href="{{ route('customers.index') }}" class="text-sm font-semibold text-leaf-700 hover:underline">Ver todos</a>
                </div>
                @if ($recentCustomers->isEmpty())
                    <div class="p-6"><x-empty-state title="Aún no hay clientes" description="Registra el primer negocio para comenzar a organizar las rutas."><x-slot:action><a href="{{ route('customers.create') }}" class="btn-primary">Crear cliente</a></x-slot:action></x-empty-state></div>
                @else
                    <div class="divide-y divide-stone-100">
                        @foreach ($recentCustomers as $customer)
                            <a href="{{ route('customers.show', $customer) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60"><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ $customer->business_name }}</p><p class="truncate text-sm text-ink-600">{{ $customer->code }} · {{ $customer->business_type ?: 'Sin tipo' }}</p></div><div class="shrink-0 text-right"><p class="font-semibold text-ink-950">{{ $customer->route_stops_count }}</p><p class="text-xs text-ink-600">visitas</p></div></a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endcan

        @can('manage-catalog')
            <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4">
                <div>
                    <h2 class="font-bold text-ink-950">Productos recientes</h2>
                    <p class="text-sm text-ink-600">Últimos registros del catálogo</p>
                </div>
                <a href="{{ route('products.index') }}" class="text-sm font-semibold text-leaf-700 hover:underline">Ver todos</a>
            </div>
            @if ($recentProducts->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Aún no hay productos" description="Registra el primer producto y su presentación base para comenzar.">
                        <x-slot:action><a href="{{ route('products.create') }}" class="btn-primary">Crear producto</a></x-slot:action>
                    </x-empty-state>
                </div>
            @else
                <div class="divide-y divide-stone-100">
                    @foreach ($recentProducts as $product)
                        <a href="{{ route('products.show', $product) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink-950">{{ $product->name }}</p>
                                <p class="truncate text-sm text-ink-600">{{ $product->sku }} · {{ $product->category->name }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold text-ink-950">{{ number_format((float) $product->basePresentation?->sale_price, 2) }}</p>
                                <p class="text-xs text-ink-600">{{ $product->basePresentation?->name }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
            </div>
        @endcan
    </section>
    @endif
@endsection
