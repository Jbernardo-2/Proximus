@extends('layouts.app')

@section('title', 'Inventario')
@section('page-title', 'Inventario')
@section('page-subtitle', 'Existencia física, mercancía comprometida y disponibilidad real.')
@section('header-actions')
    @can('operate-inventory')<a href="{{ route('inventory-documents.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo movimiento</span></a>@endcan
@endsection

@section('content')
    <div class="space-y-6">
        @can('operate-inventory')
            <nav class="flex flex-wrap gap-2 text-sm">
                <a class="btn-secondary border-leaf-600 text-leaf-700" href="{{ route('inventory.index') }}">Existencias</a>
                <a class="btn-secondary" href="{{ route('inventory-documents.index') }}">Entradas y ajustes</a>
                <a class="btn-secondary" href="{{ route('inventory-counts.index') }}">Conteos físicos</a>
                <a class="btn-secondary" href="{{ route('inventory-movements.index') }}">Bitácora</a>
                @can('configure-inventory')<a class="btn-secondary" href="{{ route('warehouses.index') }}">Bodegas</a>@endcan
            </nav>
        @endcan

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Productos controlados</p><p class="mt-2 text-3xl font-black">{{ number_format($metrics['products']) }}</p><p class="mt-1 text-xs text-ink-600">{{ number_format($metrics['with_stock']) }} con existencia física</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Con faltante</p><p class="mt-2 text-3xl font-black text-red-700">{{ number_format($metrics['shortages']) }}</p><p class="mt-1 text-xs text-ink-600">Comprometido supera lo existente</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">En punto de reposición</p><p class="mt-2 text-3xl font-black text-amber-700">{{ number_format($metrics['low_stock']) }}</p><p class="mt-1 text-xs text-ink-600">Disponible igual o menor al mínimo</p></article>
            <article class="card p-5"><p class="text-sm font-semibold text-ink-600">Bodega seleccionada</p><p class="mt-2 truncate text-xl font-black">{{ $selectedWarehouse?->name ?? 'Sin bodegas' }}</p><p class="mt-1 text-xs text-ink-600">{{ $selectedWarehouse?->code }}</p></article>
        </section>

        <section class="card p-5">
            <form method="GET" action="{{ route('inventory.index') }}" class="grid gap-4 md:grid-cols-4">
                <div><label class="form-label" for="search">Buscar producto</label><input class="form-input" id="search" name="search" value="{{ $search }}" placeholder="Nombre o SKU"></div>
                <div><label class="form-label" for="warehouse_id">Bodega</label><select class="form-input" id="warehouse_id" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($selectedWarehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="status">Situación</label><select class="form-input" id="status" name="status"><option value="">Todas</option><option value="shortage" @selected($selectedStatus === 'shortage')>Con faltante</option><option value="low" @selected($selectedStatus === 'low')>Reposición</option><option value="available" @selected($selectedStatus === 'available')>Disponibles</option><option value="zero" @selected($selectedStatus === 'zero')>Sin actividad</option></select></div>
                <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filtrar</button><a class="btn-secondary" href="{{ route('inventory.index') }}">Limpiar</a></div>
            </form>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-bold text-ink-950">Existencias por producto</h2><p class="text-sm text-ink-600">Las cantidades se expresan en la unidad base definida para cada producto.</p></div>
            @if($stocks->isEmpty())
                <div class="p-6"><x-empty-state title="Sin resultados" description="No hay productos que coincidan con los filtros seleccionados." /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading text-right">Físico</th><th class="table-heading text-right">Comprometido</th><th class="table-heading text-right">Disponible</th><th class="table-heading">Estado</th>@can('configure-inventory')<th class="table-heading">Reposición</th>@endcan</tr></thead>
                        <tbody>
                            @foreach($stocks as $stock)
                                @php($available = $stock->availableQuantity())
                                <tr>
                                    <td class="table-cell"><p class="font-semibold text-ink-950">{{ $stock->product->name }}</p><p class="text-xs text-ink-600">{{ $stock->product->sku }} · {{ $stock->product->baseUnit->symbol }}</p></td>
                                    <td class="table-cell text-right font-semibold">{{ rtrim(rtrim($stock->quantity_on_hand, '0'), '.') }}</td>
                                    <td class="table-cell text-right font-semibold">{{ rtrim(rtrim($stock->quantity_reserved, '0'), '.') }}</td>
                                    <td class="table-cell text-right text-lg font-black {{ bccomp($available, '0', 6) < 0 ? 'text-red-700' : 'text-ink-950' }}">{{ rtrim(rtrim($available, '0'), '.') }}</td>
                                    <td class="table-cell">@if(bccomp($available, '0', 6) < 0)<span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800">Faltan {{ rtrim(rtrim($stock->shortageQuantity(), '0'), '.') }}</span>@elseif($stock->isLowStock())<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Reponer</span>@else<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Disponible</span>@endif</td>
                                    @can('configure-inventory')<td class="table-cell"><form method="POST" action="{{ route('inventory-stocks.update', $stock) }}" class="flex items-center gap-2">@csrf @method('PUT')<input class="form-input min-h-9 w-28 py-1.5" name="reorder_point" type="number" min="0" step="0.000001" value="{{ $stock->reorder_point }}" aria-label="Punto de reposición de {{ $stock->product->name }}"><button class="btn-secondary min-h-9 px-3 py-1">Guardar</button></form></td>@endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-stone-100 px-5 py-4">{{ $stocks->links() }}</div>
            @endif
        </section>

        @can('operate-inventory')
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4"><div><h2 class="font-bold">Movimientos recientes</h2><p class="text-sm text-ink-600">Últimos cambios físicos o compromisos de pedidos.</p></div><a class="text-sm font-semibold text-leaf-700" href="{{ route('inventory-movements.index') }}">Ver bitácora</a></div>
                <div class="divide-y divide-stone-100">@forelse($recentMovements as $movement)<div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div><p class="font-semibold">{{ $movement->product_name }}</p><p class="text-xs text-ink-600">{{ $movement->type->label() }} · {{ $movement->reference_number ?: 'Sin referencia' }}</p></div><div class="text-right"><p class="font-bold {{ bccomp($movement->quantity_on_hand_delta, '0', 6) < 0 || bccomp($movement->quantity_reserved_delta, '0', 6) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Físico {{ (float)$movement->quantity_on_hand_delta >= 0 ? '+' : '' }}{{ rtrim(rtrim($movement->quantity_on_hand_delta, '0'), '.') }} · Reserva {{ (float)$movement->quantity_reserved_delta >= 0 ? '+' : '' }}{{ rtrim(rtrim($movement->quantity_reserved_delta, '0'), '.') }}</p><p class="text-xs text-ink-600">{{ $movement->occurred_at->format('d/m/Y H:i') }}</p></div></div>@empty<div class="p-6 text-sm text-ink-600">Aún no hay movimientos registrados.</div>@endforelse</div>
            </section>
        @endcan
    </div>
@endsection
