@extends('layouts.app')

@section('title', 'Proveedores')
@section('page-title', 'Proveedores')
@section('page-subtitle', 'Registra contactos, referencias y costos de compra.')
@section('header-actions')<a href="{{ route('suppliers.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo proveedor</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4"><form method="GET" class="flex w-full max-w-md gap-2"><input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Nombre, código o contacto…"><button class="btn-secondary">Buscar</button></form><p class="mt-3 text-sm text-ink-600 sm:mt-0">{{ $suppliers->total() }} registrados</p></div>
        @if($suppliers->isEmpty())
            <div class="p-6"><x-empty-state title="No hay proveedores" description="Agrégalos para guardar costos de compra por presentación."><x-slot:action><a href="{{ route('suppliers.create') }}" class="btn-primary">Crear proveedor</a></x-slot:action></x-empty-state></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[820px]"><thead class="bg-stone-50"><tr><th class="table-heading">Proveedor</th><th class="table-heading">Contacto</th><th class="table-heading">Productos</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead><tbody>
                @foreach($suppliers as $supplier)<tr class="hover:bg-mint-50/40"><td class="table-cell"><p class="font-semibold text-ink-950">{{ $supplier->name }}</p><p class="text-xs text-ink-600">{{ $supplier->code ?: 'Sin código' }}</p></td><td class="table-cell"><p>{{ $supplier->contact_name ?: '—' }}</p><p class="text-xs text-ink-600">{{ $supplier->phone ?: $supplier->email }}</p></td><td class="table-cell">{{ $supplier->product_sources_count }}</td><td class="table-cell"><x-status-badge :active="$supplier->is_active" /></td><td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('suppliers.edit', $supplier) }}">Editar</a><form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="¿Eliminar este proveedor?">@csrf @method('DELETE')<button class="btn-danger">Eliminar</button></form></div></td></tr>@endforeach
            </tbody></table></div><div class="border-t border-stone-100 px-4 py-4">{{ $suppliers->links() }}</div>
        @endif
    </div>
@endsection
