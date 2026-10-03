@extends('layouts.app')

@section('title', 'Categorías')
@section('page-title', 'Categorías')
@section('page-subtitle', 'Organiza el catálogo en familias y subcategorías.')
@section('header-actions')<a href="{{ route('categories.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva categoría</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
            <form method="GET" class="flex w-full max-w-md gap-2">
                <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Buscar categoría…">
                <button class="btn-secondary" type="submit">Buscar</button>
            </form>
            <p class="mt-3 text-sm text-ink-600 sm:mt-0">{{ $categories->total() }} registradas</p>
        </div>

        @if ($categories->isEmpty())
            <div class="p-6"><x-empty-state title="No hay categorías" description="Crea familias para ordenar los productos y facilitar la búsqueda."><x-slot:action><a href="{{ route('categories.create') }}" class="btn-primary">Crear categoría</a></x-slot:action></x-empty-state></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead class="bg-stone-50"><tr><th class="table-heading">Categoría</th><th class="table-heading">Categoría superior</th><th class="table-heading">Productos</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr class="hover:bg-mint-50/40">
                                <td class="table-cell"><p class="font-semibold text-ink-950">{{ $category->name }}</p><p class="max-w-sm truncate text-xs text-ink-600">{{ $category->description ?: 'Sin descripción' }}</p></td>
                                <td class="table-cell">{{ $category->parent?->name ?? '—' }}</td>
                                <td class="table-cell">{{ $category->products_count }}</td>
                                <td class="table-cell"><x-status-badge :active="$category->is_active" /></td>
                                <td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('categories.edit', $category) }}">Editar</a><form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="¿Eliminar esta categoría?"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE"><button class="btn-danger">Eliminar</button></form></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-4">{{ $categories->links() }}</div>
        @endif
    </div>
@endsection
