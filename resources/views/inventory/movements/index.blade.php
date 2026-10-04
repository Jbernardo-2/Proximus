@extends('layouts.app')

@section('title', 'Bitácora de inventario')
@section('page-title', 'Bitácora de inventario')
@section('page-subtitle', 'Registro cronológico e inmutable de existencias y reservas.')
@section('header-actions')<a href="{{ route('inventory.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <div class="space-y-6">
        <section class="card p-5"><form method="GET" class="grid gap-4 md:grid-cols-4"><div><label class="form-label">Buscar</label><input class="form-input" name="search" value="{{ $search }}" placeholder="Producto, SKU, referencia o lote"></div><div><label class="form-label">Bodega</label><select class="form-input" name="warehouse_id"><option value="">Todas</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div><div><label class="form-label">Tipo</label><select class="form-input" name="type"><option value="">Todos</option>@foreach($types as $movementType)<option value="{{ $movementType->value }}" @selected($selectedType === $movementType->value)>{{ $movementType->label() }}</option>@endforeach</select></div><div class="flex items-end gap-2"><button class="btn-primary flex-1">Filtrar</button><a class="btn-secondary" href="{{ route('inventory-movements.index') }}">Limpiar</a></div></form></section>

        <section class="card overflow-hidden">
            @if($movements->isEmpty())<div class="p-6"><x-empty-state title="Sin movimientos" description="La bitácora se llenará al aplicar entradas, ajustes, conteos o reservas de pedidos." /></div>@else
                <div class="overflow-x-auto"><table class="w-full min-w-[1100px]"><thead class="bg-stone-50"><tr><th class="table-heading">Fecha</th><th class="table-heading">Producto</th><th class="table-heading">Movimiento</th><th class="table-heading text-right">Cambio físico</th><th class="table-heading text-right">Cambio reserva</th><th class="table-heading text-right">Saldo físico</th><th class="table-heading text-right">Disponible</th><th class="table-heading">Responsable</th></tr></thead><tbody>
                    @foreach($movements as $movement)
                        <tr class="align-top"><td class="table-cell whitespace-nowrap">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td><td class="table-cell"><p class="font-semibold">{{ $movement->product_name }}</p><p class="text-xs text-ink-600">{{ $movement->product_sku }} · {{ $movement->base_unit_symbol }}</p>@if($movement->lot_number)<p class="mt-1 text-xs text-ink-600">Lote: {{ $movement->lot_number }}@if($movement->expiration_date) · vence {{ $movement->expiration_date->format('d/m/Y') }}@endif</p>@endif</td><td class="table-cell"><p class="font-semibold">{{ $movement->type->label() }}</p><p class="text-xs text-ink-600">{{ $movement->reference_number ?: 'Sin referencia' }}</p>@if($movement->reason)<p class="mt-1 max-w-sm text-xs text-ink-600">{{ $movement->reason }}</p>@endif</td><td class="table-cell text-right font-bold">{{ bccomp($movement->quantity_on_hand_delta, '0', 6) > 0 ? '+' : '' }}{{ rtrim(rtrim($movement->quantity_on_hand_delta, '0'), '.') }}</td><td class="table-cell text-right font-bold">{{ bccomp($movement->quantity_reserved_delta, '0', 6) > 0 ? '+' : '' }}{{ rtrim(rtrim($movement->quantity_reserved_delta, '0'), '.') }}</td><td class="table-cell text-right">{{ rtrim(rtrim($movement->quantity_on_hand_after, '0'), '.') }}</td><td class="table-cell text-right font-black {{ bccomp(bcsub($movement->quantity_on_hand_after, $movement->quantity_reserved_after, 6), '0', 6) < 0 ? 'text-red-700' : '' }}">{{ rtrim(rtrim(bcsub($movement->quantity_on_hand_after, $movement->quantity_reserved_after, 6), '0'), '.') }}</td><td class="table-cell"><p>{{ $movement->creator->name }}</p><p class="text-xs text-ink-600">{{ $movement->warehouse->name }}</p></td></tr>
                    @endforeach
                </tbody></table></div><div class="border-t border-stone-100 px-5 py-4">{{ $movements->links() }}</div>
            @endif
        </section>
    </div>
@endsection
