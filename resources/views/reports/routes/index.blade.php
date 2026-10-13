@extends('layouts.app')

@section('title', 'Reportes de ruta')
@section('page-title', 'Reportes de ruta')
@section('page-subtitle', 'Consolidados de carga y detalle de pedidos por jornada y ruta.')

@section('content')
    <div class="space-y-6">
        <section class="card p-5">
            <form method="GET" action="{{ route('route-reports.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label class="form-label" for="date_from">Desde</label>
                    <input class="form-input" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div>
                    <label class="form-label" for="date_to">Hasta</label>
                    <input class="form-input" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div>
                    <label class="form-label" for="route_id">Ruta</label>
                    <select class="form-input" id="route_id" name="route_id">
                        <option value="">Todas</option>
                        @foreach ($salesRoutes as $route)
                            <option value="{{ $route->id }}" @selected(($filters['route_id'] ?? '') === $route->id)>{{ $route->code }} · {{ $route->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salesperson_id">Preventista</label>
                    <select class="form-input" id="salesperson_id" name="salesperson_id">
                        <option value="">Todos</option>
                        @foreach ($salespeople as $salesperson)
                            <option value="{{ $salesperson->id }}" @selected((string) ($filters['salesperson_id'] ?? '') === (string) $salesperson->id)>{{ $salesperson->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="driver_id">Repartidor</label>
                    <select class="form-input" id="driver_id" name="driver_id">
                        <option value="">Todos</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) ($filters['driver_id'] ?? '') === (string) $driver->id)>{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-input" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 md:col-span-2 xl:col-span-6 xl:justify-end">
                    <a class="btn-secondary" href="{{ route('route-reports.index') }}">Limpiar</a>
                    <button class="btn-primary">Filtrar reportes</button>
                </div>
            </form>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-stone-100 px-5 py-4">
                <h2 class="font-bold text-ink-950">Jornadas disponibles</h2>
                <p class="text-sm text-ink-600">Cada ruta produce su propio consolidado, aunque comparta jornada con otras rutas.</p>
            </div>

            @forelse ($deliveryRuns as $deliveryRun)
                @php($routeReports = $reportsByRun->get($deliveryRun->id, collect()))
                @if ($routeReports->isNotEmpty())
                    <article class="border-b border-stone-100 p-5 last:border-0">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-black text-ink-950">{{ $deliveryRun->run_number }}</h3>
                                    <x-delivery-run-status-badge :status="$deliveryRun->status" />
                                </div>
                                <p class="mt-1 text-sm text-ink-600">
                                    {{ $deliveryRun->scheduled_date->format('d/m/Y') }} · {{ $deliveryRun->warehouse_name }} ·
                                    {{ $deliveryRun->driver_name }}
                                    @if ($deliveryRun->vehicle_license_plate)
                                        · {{ $deliveryRun->vehicle_license_plate }}
                                    @endif
                                </p>
                            </div>
                            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-ink-700">{{ $routeReports->count() }} {{ $routeReports->count() === 1 ? 'ruta' : 'rutas' }}</span>
                        </div>

                        <div class="mt-4 grid gap-3 xl:grid-cols-2">
                            @foreach ($routeReports as $report)
                                <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="font-black text-ink-950">{{ $report['route']->code }} · {{ $report['route']->name }}</p>
                                            <p class="text-sm text-ink-600">
                                                {{ $report['orders_count'] }} {{ $report['orders_count'] === 1 ? 'pedido' : 'pedidos' }}
                                                @if ($report['route']->salesperson)
                                                    · {{ $report['route']->salesperson->name }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex gap-2">
                                            <a class="btn-secondary" target="_blank" rel="noopener" href="{{ route('route-reports.print', [$deliveryRun, $report['route']]) }}">Imprimir</a>
                                            <a class="btn-primary" href="{{ route('route-reports.download', [$deliveryRun, $report['route']]) }}">Descargar</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endif
            @empty
                <div class="p-6">
                    <x-empty-state title="Sin reportes para estos filtros" description="Los reportes aparecerán cuando una jornada tenga pedidos asociados a una ruta." />
                </div>
            @endforelse

            @if ($deliveryRuns->hasPages())
                <div class="border-t border-stone-100 px-5 py-4">{{ $deliveryRuns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
