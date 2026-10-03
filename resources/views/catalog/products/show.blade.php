@extends('layouts.app')

@section('title', $product->name)
@section('page-title', $product->name)
@section('page-subtitle', $product->sku.' · '.$product->category->name)
@section('header-actions')
    <a href="{{ route('products.edit', $product) }}" class="btn-secondary">Editar</a>
    <a href="{{ route('products.index') }}" class="btn-primary">Volver</a>
@endsection

@section('content')
    <section class="grid gap-5 xl:grid-cols-[1.25fr_0.75fr]">
        <article class="card p-5 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                @if($product->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" class="size-28 rounded-2xl object-cover">
                @else
                    <div class="grid size-28 shrink-0 place-items-center rounded-2xl bg-mint-100 text-4xl font-black text-leaf-700">{{ str($product->name)->substr(0, 1)->upper() }}</div>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><x-status-badge :active="$product->is_active" /><span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700">{{ $product->allows_decimal ? 'Admite decimales' : 'Cantidades enteras' }}</span></div>
                    <h2 class="mt-3 text-2xl font-black tracking-tight text-ink-950">{{ $product->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-600">{{ $product->description ?: 'Sin descripción registrada.' }}</p>
                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-ink-600">Marca</dt><dd class="font-semibold">{{ $product->brand?->name ?? 'Sin marca' }}</dd></div>
                        <div><dt class="text-xs text-ink-600">Unidad base</dt><dd class="font-semibold">{{ $product->baseUnit->name }} ({{ $product->baseUnit->symbol }})</dd></div>
                        <div><dt class="text-xs text-ink-600">Presentaciones</dt><dd class="font-semibold">{{ $product->presentations->count() }}</dd></div>
                    </dl>
                </div>
            </div>
        </article>

        <article class="card p-5 sm:p-6">
            <p class="text-sm font-semibold text-leaf-700">Simulador de conversión</p>
            <h2 class="mt-1 text-lg font-bold text-ink-950">¿Cómo conviene despacharlo?</h2>
            <p class="mt-1 text-sm leading-6 text-ink-600">Escribe una cantidad en {{ str($product->baseUnit->name)->lower() }}. El resultado es solo una sugerencia.</p>
            <form data-conversion-form method="GET" action="{{ route('products.conversion-preview', $product) }}" class="mt-4 flex gap-2">
                <input class="form-input" type="number" name="quantity" min="0.000001" step="{{ $product->allows_decimal ? '0.001' : '1' }}" required placeholder="Ej. 30">
                <button class="btn-primary shrink-0" type="submit">Calcular</button>
            </form>
            <div data-conversion-output class="mt-4"><p class="text-xs text-ink-600">Se usarán las presentaciones activas y sus precios vigentes.</p></div>
        </article>
    </section>

    <section class="mt-7">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-xl font-black text-ink-950">Presentaciones y precios</h2><p class="text-sm text-ink-600">Cada presentación tiene su propio precio y contenido en unidad base.</p></div>
            <span class="rounded-full bg-mint-100 px-3 py-1.5 text-xs font-semibold text-leaf-700">Base: 1 {{ $product->baseUnit->symbol }}</span>
        </div>

        <div class="space-y-4">
            @foreach($product->presentations as $presentation)
                <article class="card overflow-hidden">
                    <div class="grid gap-4 p-5 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-ink-950">{{ $presentation->name }}</h3>@if($presentation->is_base)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">BASE</span>@endif<x-status-badge :active="$presentation->is_active" /></div>
                            <p class="mt-1 text-sm text-ink-600">1 {{ $presentation->name }} = {{ rtrim(rtrim($presentation->conversion_factor, '0'), '.') }} {{ $product->baseUnit->symbol }} · Código: {{ $presentation->barcode ?: 'no registrado' }}</p>
                        </div>
                        <div class="sm:text-right"><p class="text-xs text-ink-600">Precio normal</p><p class="text-xl font-black text-ink-950">{{ number_format((float) $presentation->sale_price, 2) }}</p></div>
                        <details class="relative">
                            <summary class="btn-secondary cursor-pointer list-none">Configurar</summary>
                            <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-4 sm:absolute sm:right-0 sm:z-10 sm:w-[36rem] sm:shadow-xl">
                                <form method="POST" action="{{ route('products.presentations.update', [$product, $presentation]) }}" class="grid gap-3 sm:grid-cols-2">@csrf @method('PUT')
                                    <div><label class="form-label">Nombre</label><input class="form-input" name="name" required value="{{ $presentation->name }}"></div>
                                    <div><label class="form-label">Código de barras</label><input class="form-input" name="barcode" value="{{ $presentation->barcode }}"></div>
                                    <div><label class="form-label">Contenido en {{ $product->baseUnit->symbol }}</label><input class="form-input" type="number" name="conversion_factor" step="0.000001" min="0.000001" required value="{{ $presentation->conversion_factor }}" @readonly($presentation->is_base)></div>
                                    <div><label class="form-label">Precio normal</label><input class="form-input" type="number" name="sale_price" step="0.0001" min="0" required value="{{ $presentation->sale_price }}"></div>
                                    <div class="sm:col-span-2 flex flex-wrap gap-4 text-sm"><input type="hidden" name="is_sellable" value="0"><label><input type="checkbox" name="is_sellable" value="1" @checked($presentation->is_sellable)> Se vende</label><input type="hidden" name="is_purchasable" value="0"><label><input type="checkbox" name="is_purchasable" value="1" @checked($presentation->is_purchasable)> Se compra</label><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($presentation->is_active) @disabled($presentation->is_base)> Activa</label>@if($presentation->is_base)<input type="hidden" name="is_active" value="1">@endif</div>
                                    <div class="sm:col-span-2 flex justify-between gap-3"><div>@unless($presentation->is_base)<button form="delete-presentation-{{ $presentation->id }}" class="btn-danger" type="submit">Eliminar</button>@endunless</div><button class="btn-primary">Guardar presentación</button></div>
                                </form>
                                @unless($presentation->is_base)<form id="delete-presentation-{{ $presentation->id }}" method="POST" action="{{ route('products.presentations.destroy', [$product, $presentation]) }}" data-confirm="¿Eliminar esta presentación?">@csrf @method('DELETE')</form>@endunless
                            </div>
                        </details>
                    </div>

                    <div class="border-t border-stone-100 bg-stone-50/60 px-5 py-4">
                        <div class="mb-3 flex items-center justify-between gap-3"><div><h4 class="text-sm font-bold text-ink-950">Precios por cantidad</h4><p class="text-xs text-ink-600">Reemplazan el precio normal cuando cantidad y vigencia coinciden.</p></div><details><summary class="cursor-pointer text-sm font-semibold text-leaf-700">＋ Agregar precio</summary><form method="POST" action="{{ route('products.presentations.price-tiers.store', [$product, $presentation]) }}" class="mt-3 grid gap-3 rounded-xl border border-stone-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">@csrf<div><label class="form-label">Desde *</label><input class="form-input" name="min_quantity" type="number" min="0.000001" step="0.000001" required></div><div><label class="form-label">Hasta</label><input class="form-input" name="max_quantity" type="number" min="0.000001" step="0.000001"></div><div><label class="form-label">Precio *</label><input class="form-input" name="unit_price" type="number" min="0" step="0.0001" required></div><div><label class="form-label">Inicia</label><input class="form-input" name="starts_at" type="date"></div><div><label class="form-label">Finaliza</label><input class="form-input" name="ends_at" type="date"></div><div class="flex items-end"><input type="hidden" name="is_active" value="1"><button class="btn-primary w-full">Agregar</button></div></form></details></div>

                        @if($presentation->priceTiers->isEmpty())
                            <p class="rounded-lg border border-dashed border-stone-300 px-3 py-2 text-xs text-ink-600">No hay precios por cantidad; se usa el precio normal.</p>
                        @else
                            <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm"><thead><tr><th class="py-2 text-left text-xs text-ink-600">Rango</th><th class="py-2 text-left text-xs text-ink-600">Precio</th><th class="py-2 text-left text-xs text-ink-600">Vigencia</th><th class="py-2 text-left text-xs text-ink-600">Estado</th><th></th></tr></thead><tbody>
                                @foreach($presentation->priceTiers as $tier)
                                    <tr class="border-t border-stone-200">
                                        <td class="py-2.5">{{ rtrim(rtrim($tier->min_quantity, '0'), '.') }} – {{ $tier->max_quantity ? rtrim(rtrim($tier->max_quantity, '0'), '.') : 'en adelante' }}</td>
                                        <td class="py-2.5 font-semibold">{{ number_format((float) $tier->unit_price, 2) }}</td>
                                        <td class="py-2.5">{{ $tier->starts_at?->format('d/m/Y') ?? 'Sin inicio' }} · {{ $tier->ends_at?->format('d/m/Y') ?? 'Sin fin' }}</td>
                                        <td class="py-2.5"><x-status-badge :active="$tier->is_active" /></td>
                                        <td class="py-2.5 text-right">
                                            <div class="flex items-start justify-end gap-3">
                                                <details class="text-left">
                                                    <summary class="cursor-pointer text-xs font-semibold text-leaf-700">Editar</summary>
                                                    <form method="POST" action="{{ route('products.presentations.price-tiers.update', [$product, $presentation, $tier]) }}" class="mt-2 grid min-w-[38rem] gap-2 rounded-xl border border-stone-200 bg-white p-3 shadow-lg sm:grid-cols-3">@csrf @method('PUT')
                                                        <input class="form-input" aria-label="Cantidad mínima" name="min_quantity" type="number" min="0.000001" step="0.000001" required value="{{ $tier->min_quantity }}">
                                                        <input class="form-input" aria-label="Cantidad máxima" name="max_quantity" type="number" min="0.000001" step="0.000001" value="{{ $tier->max_quantity }}" placeholder="Sin máximo">
                                                        <input class="form-input" aria-label="Precio" name="unit_price" type="number" min="0" step="0.0001" required value="{{ $tier->unit_price }}">
                                                        <input class="form-input" aria-label="Fecha de inicio" name="starts_at" type="date" value="{{ $tier->starts_at?->toDateString() }}">
                                                        <input class="form-input" aria-label="Fecha final" name="ends_at" type="date" value="{{ $tier->ends_at?->toDateString() }}">
                                                        <div class="flex items-center justify-between gap-2"><input type="hidden" name="is_active" value="0"><label class="text-xs"><input type="checkbox" name="is_active" value="1" @checked($tier->is_active)> Activo</label><button class="btn-primary min-h-9 px-3 py-1">Guardar</button></div>
                                                    </form>
                                                </details>
                                                <form method="POST" action="{{ route('products.presentations.price-tiers.destroy', [$product, $presentation, $tier]) }}" data-confirm="¿Eliminar este precio por cantidad?">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700 hover:underline">Eliminar</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody></table></div>
                        @endif
                    </div>
                </article>
            @endforeach

            <details class="card p-5">
                <summary class="cursor-pointer list-none font-bold text-leaf-700">＋ Agregar caja, paquete, fardo u otra presentación</summary>
                <form method="POST" action="{{ route('products.presentations.store', $product) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@csrf
                    <div><label class="form-label">Nombre *</label><input class="form-input" name="name" required placeholder="Ej. Caja 24"></div><div><label class="form-label">Código de barras</label><input class="form-input" name="barcode"></div><div><label class="form-label">Contenido en {{ $product->baseUnit->symbol }} *</label><input class="form-input" name="conversion_factor" type="number" min="0.000001" step="0.000001" required placeholder="24"><p class="form-help">1 presentación equivale a esta cantidad base.</p></div><div><label class="form-label">Precio normal *</label><input class="form-input" name="sale_price" type="number" min="0" step="0.0001" required></div><div class="sm:col-span-2 xl:col-span-4 flex flex-wrap items-center justify-between gap-4"><div class="flex flex-wrap gap-4 text-sm"><input type="hidden" name="is_sellable" value="0"><label><input type="checkbox" name="is_sellable" value="1" checked> Se vende</label><input type="hidden" name="is_purchasable" value="0"><label><input type="checkbox" name="is_purchasable" value="1" checked> Se compra</label><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" checked> Activa</label></div><button class="btn-primary">Agregar presentación</button></div>
                </form>
            </details>
        </div>
    </section>

    <section class="mt-8">
        <div class="mb-4"><h2 class="text-xl font-black text-ink-950">Proveedores y costos</h2><p class="text-sm text-ink-600">Un mismo producto puede comprarse a distintos proveedores y en distintas presentaciones.</p></div>
        <div class="card overflow-hidden">
            @if($product->productSuppliers->isEmpty())
                <div class="p-5 text-sm text-ink-600">Aún no hay proveedores vinculados.</div>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[760px]"><thead class="bg-stone-50"><tr><th class="table-heading">Proveedor</th><th class="table-heading">Presentación</th><th class="table-heading">Referencia</th><th class="table-heading">Costo</th><th class="table-heading">Estado</th><th class="table-heading"></th></tr></thead><tbody>
                    @foreach($product->productSuppliers as $source)
                        <tr>
                            <td class="table-cell"><p class="font-semibold text-ink-950">{{ $source->supplier->name }}</p>@if($source->is_preferred)<span class="text-xs font-semibold text-leaf-700">Proveedor preferido</span>@endif</td>
                            <td class="table-cell">{{ $source->presentation->name }}</td>
                            <td class="table-cell">{{ $source->supplier_sku ?: '—' }}</td>
                            <td class="table-cell font-semibold">{{ number_format((float) $source->cost_price, 2) }}</td>
                            <td class="table-cell"><x-status-badge :active="$source->is_active" /></td>
                            <td class="table-cell text-right">
                                <div class="flex items-start justify-end gap-3">
                                    <details class="text-left">
                                        <summary class="cursor-pointer text-xs font-semibold text-leaf-700">Editar</summary>
                                        <form method="POST" action="{{ route('products.suppliers.update', [$product, $source]) }}" class="mt-2 grid min-w-[42rem] gap-2 rounded-xl border border-stone-200 bg-white p-3 shadow-lg sm:grid-cols-4">@csrf @method('PUT')
                                            <select class="form-input" aria-label="Proveedor" name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($source->supplier_id === $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>
                                            <select class="form-input" aria-label="Presentación" name="product_presentation_id" required>@foreach($product->presentations as $option)<option value="{{ $option->id }}" @selected($source->product_presentation_id === $option->id)>{{ $option->name }}</option>@endforeach</select>
                                            <input class="form-input" aria-label="Referencia del proveedor" name="supplier_sku" value="{{ $source->supplier_sku }}" placeholder="Referencia">
                                            <input class="form-input" aria-label="Costo" name="cost_price" type="number" min="0" step="0.0001" required value="{{ $source->cost_price }}">
                                            <textarea class="form-input sm:col-span-2" aria-label="Notas" name="notes" placeholder="Notas">{{ $source->notes }}</textarea>
                                            <div class="flex items-center gap-3 text-xs"><input type="hidden" name="is_preferred" value="0"><label><input type="checkbox" name="is_preferred" value="1" @checked($source->is_preferred)> Preferido</label><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($source->is_active)> Activo</label></div>
                                            <div class="flex justify-end"><button class="btn-primary min-h-9 px-3 py-1">Guardar</button></div>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('products.suppliers.destroy', [$product, $source]) }}" data-confirm="¿Quitar este proveedor del producto?">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700 hover:underline">Quitar</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <details class="border-t border-stone-100 p-5">
                <summary class="cursor-pointer list-none text-sm font-bold text-leaf-700">＋ Vincular proveedor</summary>
                @if($suppliers->isEmpty())
                    <p class="mt-3 text-sm text-ink-600">Primero crea un proveedor en el módulo de proveedores.</p>
                @else
                    <form method="POST" action="{{ route('products.suppliers.store', $product) }}" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">@csrf<div><label class="form-label">Proveedor *</label><select class="form-input" name="supplier_id" required><option value="">Selecciona…</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div><div><label class="form-label">Presentación *</label><select class="form-input" name="product_presentation_id" required><option value="">Selecciona…</option>@foreach($product->presentations as $presentation)<option value="{{ $presentation->id }}">{{ $presentation->name }}</option>@endforeach</select></div><div><label class="form-label">Referencia del proveedor</label><input class="form-input" name="supplier_sku"></div><div><label class="form-label">Costo *</label><input class="form-input" name="cost_price" type="number" min="0" step="0.0001" required></div><div class="flex items-end"><button class="btn-primary w-full">Vincular</button></div><div class="sm:col-span-2 xl:col-span-5 flex flex-wrap gap-4 text-sm"><input type="hidden" name="is_preferred" value="0"><label><input type="checkbox" name="is_preferred" value="1"> Marcar como preferido</label><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" checked> Vínculo activo</label></div><div class="sm:col-span-2 xl:col-span-5"><label class="form-label">Notas</label><textarea class="form-input" name="notes"></textarea></div></form>
                @endif
            </details>
        </div>
    </section>

    <section class="mt-8 flex justify-end">
        <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="¿Archivar este producto? Ya no aparecerá en la operación normal.">@csrf @method('DELETE')<button class="btn-danger">Archivar producto</button></form>
    </section>
@endsection
