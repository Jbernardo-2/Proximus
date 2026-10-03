@extends('layouts.app')

@section('title', 'Productos')
@section('page-title', 'Productos')
@section('page-subtitle', 'Presentaciones, precios, códigos y proveedores desde un solo lugar.')
@section('header-actions')<a href="{{ route('products.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo producto</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-stone-100 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,1fr)_13rem_13rem_10rem_auto]">
            <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Nombre, SKU o código de barras…">
            <select class="form-input" name="category_id"><option value="">Todas las categorías</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') === $category->id)>{{ $category->name }}</option>@endforeach</select>
            <select class="form-input" name="brand_id"><option value="">Todas las marcas</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(request('brand_id') === $brand->id)>{{ $brand->name }}</option>@endforeach</select>
            <select class="form-input" name="status"><option value="">Cualquier estado</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select>
            <div class="flex gap-2"><button class="btn-secondary flex-1">Filtrar</button>@if(request()->query())<a href="{{ route('products.index') }}" class="btn-secondary px-3" aria-label="Limpiar filtros">×</a>@endif</div>
        </form>

        @if($products->isEmpty())
            <div class="p-6"><x-empty-state title="No encontramos productos" description="Crea el primer producto o ajusta los filtros de búsqueda."><x-slot:action><a href="{{ route('products.create') }}" class="btn-primary">Crear producto</a></x-slot:action></x-empty-state></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[920px]"><thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading">Categoría / marca</th><th class="table-heading">Unidad base</th><th class="table-heading">Precio base</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead><tbody>
                @foreach($products as $product)
                    <tr class="group hover:bg-mint-50/40">
                        <td class="table-cell"><div class="flex items-center gap-3">@if($product->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="" class="size-11 rounded-xl object-cover">@else<span class="grid size-11 shrink-0 place-items-center rounded-xl bg-mint-100 font-bold text-leaf-700">{{ str($product->name)->substr(0, 1)->upper() }}</span>@endif<div class="min-w-0"><a href="{{ route('products.show', $product) }}" class="block truncate font-semibold text-ink-950 group-hover:text-leaf-700">{{ $product->name }}</a><p class="text-xs text-ink-600">{{ $product->sku }}</p></div></div></td>
                        <td class="table-cell"><p>{{ $product->category->name }}</p><p class="text-xs text-ink-600">{{ $product->brand?->name ?? 'Sin marca' }}</p></td>
                        <td class="table-cell">{{ $product->baseUnit->name }} <span class="text-xs text-ink-600">({{ $product->baseUnit->symbol }})</span></td>
                        <td class="table-cell"><p class="font-semibold text-ink-950">{{ number_format((float) $product->basePresentation?->sale_price, 2) }}</p><p class="text-xs text-ink-600">por {{ str($product->basePresentation?->name)->lower() }}</p></td>
                        <td class="table-cell"><x-status-badge :active="$product->is_active" /></td>
                        <td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('products.show', $product) }}">Ver</a><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('products.edit', $product) }}">Editar</a></div></td>
                    </tr>
                @endforeach
            </tbody></table></div><div class="border-t border-stone-100 px-4 py-4">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
