@extends('layouts.app')

@section('title', 'Preparar '.$runOrder->order->order_number)
@section('page-title', 'Preparar pedido '.$runOrder->order->order_number)
@section('page-subtitle', 'Parada #'.$runOrder->visit_order.' · '.$runOrder->order->customer_name)
@section('header-actions')<a href="{{ route('delivery-runs.show', $deliveryRun) }}#preparation-queue" class="btn-secondary">Volver a pendientes</a>@endsection

@section('content')
    @php($quantity = static fn ($value): string => rtrim(rtrim((string) $value, '0'), '.') ?: '0')
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="grid gap-4 sm:grid-cols-3">
            <article class="card p-5"><p class="text-xs font-bold uppercase text-ink-600">Cliente</p><p class="mt-2 font-black text-ink-950">{{ $runOrder->order->customer_name }}</p><p class="text-xs text-ink-600">{{ $runOrder->order->customer_code }}</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase text-ink-600">Ruta</p><p class="mt-2 font-black text-ink-950">{{ $runOrder->order->route_name ?: 'Fuera de ruta' }}</p><p class="text-xs text-ink-600">Parada #{{ $runOrder->visit_order }}</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase text-ink-600">Líneas</p><p class="mt-1 text-3xl font-black text-ink-950">{{ $runOrder->items->count() }}</p><p class="text-xs text-ink-600">Bodega: {{ $deliveryRun->warehouse_name }}</p></article>
        </section>

        <form method="POST" action="{{ route('delivery-runs.orders.preparation.update', [$deliveryRun, $runOrder]) }}" class="card overflow-hidden" data-preparation-form>
            @csrf @method('PUT')
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 bg-amber-50 px-5 py-4">
                <div><h2 class="font-black text-amber-950">Compara solicitado contra preparado</h2><p class="text-sm text-amber-900">La cantidad puede ser menor por faltante, pero nunca mayor.</p></div>
                <button class="btn-secondary" type="button" data-fill-all-requested>Copiar todo lo solicitado</button>
            </header>
            <div class="divide-y divide-stone-100">
                @foreach($runOrder->items as $item)
                    <div class="grid items-center gap-4 p-5 md:grid-cols-[minmax(0,1fr)_11rem_11rem]">
                        <div>
                            <p class="font-black text-ink-950">{{ $item->product_name }}</p>
                            <p class="text-sm font-semibold text-leaf-700">{{ $item->presentation_name }} · {{ $item->product_sku }}</p>
                            <p class="mt-1 text-xs text-ink-600">1 {{ $item->presentation_name }} = {{ $quantity($item->conversion_factor) }} {{ $item->base_unit_symbol }}</p>
                        </div>
                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-center">
                            <p class="text-xs font-bold uppercase text-sky-800">Solicitado</p>
                            <p class="mt-1 text-2xl font-black text-sky-950">{{ $quantity($item->requested_quantity) }}</p>
                            <p class="text-xs text-sky-800">{{ $item->presentation_name }}</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-2"><label class="form-label mb-1" for="prepared-{{ $item->id }}">Preparado</label><button class="text-xs font-bold text-leaf-700 hover:underline" type="button" data-fill-requested="{{ $quantity($item->requested_quantity) }}">Usar solicitado</button></div>
                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                            <input class="form-input text-lg font-black" id="prepared-{{ $item->id }}" name="items[{{ $loop->index }}][prepared_quantity]" type="number" inputmode="decimal" min="0" max="{{ $item->requested_quantity }}" step="0.000001" required value="{{ old("items.{$loop->index}.prepared_quantity", $quantity($item->prepared_quantity)) }}" data-zero-safe-quantity data-requested-quantity="{{ $quantity($item->requested_quantity) }}">
                        </div>
                    </div>
                @endforeach
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 bg-stone-50 px-5 py-4">
                <p class="max-w-xl text-sm text-ink-600">Guardar este pedido no descuenta inventario. La salida se aplicará una sola vez cuando toda la jornada esté lista.</p>
                <button class="btn-primary" type="submit">Guardar y volver a pendientes</button>
            </footer>
        </form>
    </div>
@endsection
