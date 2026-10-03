@extends('layouts.app')

@section('title', 'Marcas')
@section('page-title', 'Marcas')
@section('page-subtitle', 'Mantén consistentes los nombres comerciales del catálogo.')
@section('header-actions')<a href="{{ route('brands.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva marca</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4"><form method="GET" class="flex w-full max-w-md gap-2"><input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Buscar marca…"><button class="btn-secondary">Buscar</button></form><p class="mt-3 text-sm text-ink-600 sm:mt-0">{{ $brands->total() }} registradas</p></div>
        @if ($brands->isEmpty())
            <div class="p-6"><x-empty-state title="No hay marcas" description="Registra las marcas que usarás al crear productos."><x-slot:action><a href="{{ route('brands.create') }}" class="btn-primary">Crear marca</a></x-slot:action></x-empty-state></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px]"><thead class="bg-stone-50"><tr><th class="table-heading">Marca</th><th class="table-heading">Productos</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead><tbody>
                @foreach($brands as $brand)<tr class="hover:bg-mint-50/40"><td class="table-cell"><p class="font-semibold text-ink-950">{{ $brand->name }}</p><p class="max-w-md truncate text-xs text-ink-600">{{ $brand->description ?: 'Sin descripción' }}</p></td><td class="table-cell">{{ $brand->products_count }}</td><td class="table-cell"><x-status-badge :active="$brand->is_active" /></td><td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('brands.edit', $brand) }}">Editar</a><form method="POST" action="{{ route('brands.destroy', $brand) }}" data-confirm="¿Eliminar esta marca?">@csrf @method('DELETE')<button class="btn-danger">Eliminar</button></form></div></td></tr>@endforeach
            </tbody></table></div><div class="border-t border-stone-100 px-4 py-4">{{ $brands->links() }}</div>
        @endif
    </div>
@endsection
