@extends('layouts.app')

@php($editing = $product->exists)
@section('title', $editing ? 'Editar producto' : 'Nuevo producto')
@section('page-title', $editing ? 'Editar producto' : 'Nuevo producto')
@section('page-subtitle', $editing ? 'Actualiza la información general; las presentaciones se manejan en el detalle.' : 'Registra el producto y su presentación mínima de venta.')

@section('content')
    @if(!$editing && ($categories->isEmpty() || $units->isEmpty()))
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Antes de continuar:</strong> necesitas al menos una categoría y una unidad de medida activas.</div>
    @endif
    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" class="space-y-6" data-product-form data-product-create="{{ $editing ? 'false' : 'true' }}">@csrf @if($editing) @method('PUT') @endif
        <section class="card p-5 sm:p-7">
            <div class="mb-6"><h2 class="text-lg font-bold text-ink-950">Información general</h2><p class="text-sm text-ink-600">Datos con los que el equipo identificará el producto.</p></div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div class="sm:col-span-2"><label class="form-label" for="name">Nombre del producto *</label><input class="form-input" id="name" name="name" required maxlength="180" value="{{ old('name', $product->name) }}" placeholder="Ej. Galleta de vainilla 30 g"></div>
                <div><label class="form-label" for="sku">SKU interno *</label><input class="form-input uppercase" id="sku" name="sku" required maxlength="80" value="{{ old('sku', $product->sku) }}" placeholder="GAL-VAI-030"></div>
                <div><label class="form-label" for="category_id">Categoría *</label><select class="form-input" id="category_id" name="category_id" required><option value="">Selecciona…</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="brand_id">Marca</label><select class="form-input" id="brand_id" name="brand_id"><option value="">Sin marca</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) === $brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="base_unit_id">Unidad base *</label><select class="form-input" id="base_unit_id" name="base_unit_id" required><option value="">Selecciona…</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('base_unit_id', $product->base_unit_id) === $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select><p class="form-help">La cantidad mínima que controla el producto.</p></div>
                <div class="sm:col-span-2 xl:col-span-3"><label class="form-label" for="description">Descripción</label><textarea class="form-input min-h-24" id="description" name="description" maxlength="4000">{{ old('description', $product->description) }}</textarea></div>
                <div class="sm:col-span-2 xl:col-span-3" data-product-image-picker>
                    <label class="form-label" for="image">Imagen del producto</label>
                    <input
                        class="sr-only"
                        id="image"
                        name="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        data-product-image-input
                    >

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button class="flex min-h-24 items-center gap-4 rounded-xl border border-stone-300 bg-white p-4 text-left transition hover:border-leaf-600 hover:bg-mint-50" type="button" data-image-source="file">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-mint-100 text-xl text-leaf-700" aria-hidden="true">▧</span>
                            <span>
                                <span class="block text-sm font-bold text-ink-950">Buscar en archivos</span>
                                <span class="mt-1 block text-xs leading-5 text-ink-600">Selecciona una imagen guardada en el dispositivo.</span>
                            </span>
                        </button>
                        <button class="flex min-h-24 items-center gap-4 rounded-xl border border-stone-300 bg-white p-4 text-left transition hover:border-leaf-600 hover:bg-mint-50" type="button" data-image-source="camera">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-mint-100 text-xl text-leaf-700" aria-hidden="true">◉</span>
                            <span>
                                <span class="block text-sm font-bold text-ink-950">Usar la cámara</span>
                                <span class="mt-1 block text-xs leading-5 text-ink-600">Toma la fotografía con la cámara del dispositivo.</span>
                            </span>
                        </button>
                    </div>

                    <div class="mt-4 {{ $editing && $product->image_path ? 'flex' : 'hidden' }} flex-col items-start gap-4 rounded-xl border border-stone-200 bg-stone-50 p-3 sm:flex-row sm:items-center" data-product-image-preview>
                        <img
                            @if($editing && $product->image_path) src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" @endif
                            class="size-24 rounded-xl bg-white object-contain"
                            alt="Vista previa del producto"
                            data-product-image-preview-image
                        >
                        <div class="mt-3 min-w-0 sm:mt-0">
                            <p class="truncate text-sm font-semibold text-ink-950" data-product-image-preview-name>{{ $editing && $product->image_path ? 'Imagen actual' : '' }}</p>
                            <p class="mt-1 text-xs leading-5 text-ink-600" data-product-image-preview-description>{{ $editing ? 'Revisa la fotografía antes de guardar los cambios.' : 'Proximus preparará una versión uniforme y la mostrará antes de guardar.' }}</p>
                            @unless($editing)
                                <button class="mt-2 hidden text-xs font-semibold text-leaf-700 underline decoration-leaf-300 underline-offset-4" type="button" data-product-image-toggle-version></button>
                            @endunless
                        </div>
                    </div>

                    @unless($editing)
                        <p class="mt-3 hidden rounded-xl border border-mint-200 bg-mint-50 p-3 text-xs leading-5 text-leaf-800" role="status" aria-live="polite" data-product-image-processing-status></p>
                    @endunless

                    <p class="form-help">
                        JPG, PNG o WebP. Máximo 10 MB.
                        @unless($editing)
                            Proximus intentará quitar el fondo dentro del navegador, lo reemplazará por blanco y preparará una imagen cuadrada. Podrás revisar el resultado o conservar la original.
                        @endunless
                    </p>
                    @if($editing && $product->image_path)
                        <label class="mt-2 flex items-center gap-2 text-sm text-ink-600">
                            <input type="checkbox" name="remove_image" value="1" data-remove-product-image>
                            Quitar imagen actual
                        </label>
                    @endif
                </div>
                <div class="space-y-3"><input type="hidden" name="allows_decimal" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="allows_decimal" value="1" @checked((bool) old('allows_decimal', $product->allows_decimal))><span><span class="block text-sm font-semibold">Admite cantidades decimales</span><span class="text-xs text-ink-600">Para peso, volumen o fracciones.</span></span></label><input type="hidden" name="is_active" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $product->exists ? $product->is_active : true))><span><span class="block text-sm font-semibold">Producto activo</span><span class="text-xs text-ink-600">Disponible para la operación.</span></span></label></div>
                <div class="space-y-3">
                    <input type="hidden" name="tracks_lots" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="tracks_lots" value="1" @checked((bool) old('tracks_lots', $product->tracks_lots))><span><span class="block text-sm font-semibold">Controlar lotes</span><span class="text-xs text-ink-600">El lote será obligatorio en cada entrada o salida.</span></span></label>
                    <input type="hidden" name="tracks_expiration" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="tracks_expiration" value="1" @checked((bool) old('tracks_expiration', $product->tracks_expiration))><span><span class="block text-sm font-semibold">Controlar vencimiento</span><span class="text-xs text-ink-600">Requiere también el control por lotes.</span></span></label>
                </div>
            </div>
        </section>

        @unless($editing)
            <section class="card p-5 sm:p-7">
                <div class="mb-6"><span class="rounded-full bg-mint-100 px-2.5 py-1 text-xs font-semibold text-leaf-700">Presentación obligatoria</span><h2 class="mt-3 text-lg font-bold text-ink-950">Presentación base</h2><p class="text-sm text-ink-600">Equivale exactamente a una unidad base. Después podrás agregar caja, paquete o fardo.</p></div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4"><div><label class="form-label" for="base_presentation_name">Nombre *</label><input class="form-input" id="base_presentation_name" name="base_presentation_name" required maxlength="120" value="{{ old('base_presentation_name', 'Unidad') }}"></div><div><label class="form-label" for="base_barcode">Código de barras</label><input class="form-input" id="base_barcode" name="base_barcode" maxlength="80" value="{{ old('base_barcode') }}"></div><div><label class="form-label" for="base_sale_price">Precio de venta *</label><input class="form-input" id="base_sale_price" name="base_sale_price" type="number" min="0" step="0.0001" required value="{{ old('base_sale_price', '0.00') }}"></div><div class="space-y-3"><input type="hidden" name="base_is_sellable" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="base_is_sellable" value="1" @checked((bool) old('base_is_sellable', true))> Se vende</label><input type="hidden" name="base_is_purchasable" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="base_is_purchasable" value="1" @checked((bool) old('base_is_purchasable', true))> Se compra</label></div></div>
            </section>
        @endunless

        <div class="flex flex-wrap justify-end gap-3"><a class="btn-secondary" href="{{ $editing ? route('products.show', $product) : route('products.index') }}">Cancelar</a><button class="btn-primary" data-product-submit @disabled(!$editing && ($categories->isEmpty() || $units->isEmpty()))>{{ $editing ? 'Guardar cambios' : 'Crear producto' }}</button></div>
    </form>
@endsection
