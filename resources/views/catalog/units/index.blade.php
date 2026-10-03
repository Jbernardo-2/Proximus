@extends('layouts.app')

@section('title', 'Unidades de medida')
@section('page-title', 'Unidades de medida')
@section('page-subtitle', 'Define la unidad mínima en la que existe cada producto.')
@section('header-actions')<a href="{{ route('measurement-units.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva unidad</span></a>@endsection

@section('content')
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><strong>Importante:</strong> una caja o un fardo no es una unidad de medida; es una presentación. La unidad base puede ser unidad, kilogramo, litro, etc.</div>
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4"><form method="GET" class="flex w-full max-w-md gap-2"><input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Buscar unidad…"><button class="btn-secondary">Buscar</button></form><p class="mt-3 text-sm text-ink-600 sm:mt-0">{{ $units->total() }} registradas</p></div>
        @if($units->isEmpty())
            <div class="p-6"><x-empty-state title="No hay unidades" description="Crea al menos una unidad antes de registrar productos."><x-slot:action><a href="{{ route('measurement-units.create') }}" class="btn-primary">Crear unidad</a></x-slot:action></x-empty-state></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[700px]"><thead class="bg-stone-50"><tr><th class="table-heading">Unidad</th><th class="table-heading">Símbolo</th><th class="table-heading">Decimales</th><th class="table-heading">Productos</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead><tbody>
                @foreach($units as $unit)<tr class="hover:bg-mint-50/40"><td class="table-cell font-semibold text-ink-950">{{ $unit->name }}</td><td class="table-cell">{{ $unit->symbol }}</td><td class="table-cell">{{ $unit->decimal_places }}</td><td class="table-cell">{{ $unit->products_count }}</td><td class="table-cell"><x-status-badge :active="$unit->is_active" /></td><td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('measurement-units.edit', $unit) }}">Editar</a><form method="POST" action="{{ route('measurement-units.destroy', $unit) }}" data-confirm="¿Eliminar esta unidad?">@csrf @method('DELETE')<button class="btn-danger">Eliminar</button></form></div></td></tr>@endforeach
            </tbody></table></div><div class="border-t border-stone-100 px-4 py-4">{{ $units->links() }}</div>
        @endif
    </div>
@endsection
