@extends('layouts.app')

@section('title', $order->order_number)
@section('page-title', $order->order_number)
@section('page-subtitle', $order->customer_name.' · '.$order->order_date->format('d/m/Y'))
@section('header-actions')<a href="{{ route('orders.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Estado</p><div class="mt-3"><x-order-status-badge :status="$order->status" /></div>@if($order->confirmed_at)<p class="mt-2 text-xs text-ink-600">Confirmado {{ $order->confirmed_at->format('d/m/Y H:i') }}</p>@endif</article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Cliente</p><p class="mt-2 font-bold text-ink-950">{{ $order->customer_name }}</p><p class="text-xs text-ink-600">{{ $order->customer_code }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Ruta</p><p class="mt-2 font-bold text-ink-950">{{ $order->route_name ?: 'Fuera de ruta' }}</p><p class="text-xs text-ink-600">{{ $order->salesperson_name }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Total interno</p><p class="mt-1 text-3xl font-black text-ink-950">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p><p class="text-xs text-ink-600">{{ $order->payment_term->label() }} · sin documento fiscal</p></article>
        </section>

        @if ($order->status === \App\OrderStatus::Cancelled)
            <section class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-900"><p class="font-bold">Pedido cancelado</p><p class="mt-1 whitespace-pre-line text-sm">{{ $order->cancellation_reason }}</p></section>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
            <div class="space-y-6">
                @if ($canUpdate)
                    <section class="card p-5 sm:p-7">
                        <div class="mb-5">
                            <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Agregar producto</p>
                            <h2 class="mt-1 text-lg font-black text-ink-950">Presentación y cantidad</h2>
                            <p class="text-sm text-ink-600">El precio normal o por cantidad se calcula solo. La conversión se muestra como sugerencia y nunca modifica el pedido automáticamente.</p>
                        </div>
                        @if ($products->isEmpty())
                            <x-empty-state title="Sin presentaciones vendibles" description="Activa al menos un producto y una presentación de venta en el catálogo." />
                        @else
                            <form method="POST" action="{{ route('orders.items.store', $order) }}" class="space-y-4" data-order-item-form data-quote-url="{{ route('orders.item-quote', $order) }}">
                                @csrf
                                <div class="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_9rem_auto]">
                                    <div>
                                        <label class="form-label" for="product_presentation_id">Producto y presentación *</label>
                                        <select class="form-input" id="product_presentation_id" name="product_presentation_id" required data-order-presentation-select>
                                            <option value="">Selecciona…</option>
                                            @foreach ($products as $product)
                                                <optgroup label="{{ $product->name }} · {{ $product->sku }}">
                                                    @foreach ($product->presentations as $presentation)
                                                        <option value="{{ $presentation->id }}" @selected(old('product_presentation_id') === $presentation->id)>{{ $presentation->name }} · 1 = {{ rtrim(rtrim($presentation->conversion_factor, '0'), '.') }} {{ $product->baseUnit->symbol }} · {{ $order->currency }} {{ number_format((float) $presentation->sale_price, 2) }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><label class="form-label" for="quantity">Cantidad *</label><input class="form-input" id="quantity" name="quantity" type="number" min="0.000001" step="0.000001" required value="{{ old('quantity', 1) }}" data-order-quantity-input></div>
                                    <div class="flex items-end"><button class="btn-secondary w-full" type="button" data-order-quote-button>Ver precio</button></div>
                                </div>
                                <div class="hidden" data-order-quote-output></div>
                                @if ($canOverridePrice)
                                    <details class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                                        <summary class="cursor-pointer text-sm font-semibold text-ink-800">Autorizar un precio diferente</summary>
                                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div><label class="form-label" for="unit_price">Precio unitario autorizado</label><input class="form-input" id="unit_price" name="unit_price" type="number" min="0" step="0.0001" value="{{ old('unit_price') }}" placeholder="Vacío usa el precio calculado"></div>
                                            <div><label class="form-label" for="override_reason">Motivo</label><input class="form-input" id="override_reason" name="override_reason" maxlength="1000" value="{{ old('override_reason') }}" placeholder="Obligatorio si cambia el precio"></div>
                                        </div>
                                    </details>
                                @endif
                                <div><label class="form-label" for="item_notes">Nota de la línea</label><input class="form-input" id="item_notes" name="notes" maxlength="1000" value="{{ old('notes') }}" placeholder="Opcional"></div>
                                <div class="flex justify-end"><button class="btn-primary" type="submit">Agregar al pedido</button></div>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h2 class="font-bold text-ink-950">Productos del pedido</h2><p class="text-sm text-ink-600">Cada presentación conserva su precio y equivalencia histórica.</p></div><p class="text-sm font-bold text-ink-950">{{ $order->items->count() }} líneas</p></div>
                    @if ($order->items->isEmpty())
                        <div class="p-6"><x-empty-state title="Borrador vacío" description="Agrega el primer producto para calcular el total y las sugerencias de presentación." /></div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px]">
                                <thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading">Cantidad</th><th class="table-heading">Precio</th><th class="table-heading text-right">Subtotal</th>@if($canUpdate)<th class="table-heading text-right">Acciones</th>@endif</tr></thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        <tr class="align-top">
                                            <td class="table-cell"><p class="font-semibold text-ink-950">{{ $item->product_name }}</p><p class="text-xs text-ink-600">{{ $item->product_sku }} · {{ $item->presentation_name }}</p>@if($item->notes)<p class="mt-1 text-xs text-ink-600">{{ $item->notes }}</p>@endif</td>
                                            <td class="table-cell"><p class="font-semibold text-ink-950">{{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->presentation_name }}</p><p class="text-xs text-ink-600">{{ rtrim(rtrim($item->base_quantity, '0'), '.') }} {{ $item->base_unit_symbol }} base</p></td>
                                            <td class="table-cell"><p class="font-semibold text-ink-950">{{ $order->currency }} {{ number_format((float) $item->unit_price, 2) }}</p><p class="text-xs text-ink-600">{{ $item->price_source->label() }}</p>@if($item->price_source === \App\OrderPriceSource::Override)<p class="mt-1 max-w-xs text-xs text-amber-700">{{ $item->override_reason }} · {{ $item->priceOverriddenBy?->name }}</p>@endif</td>
                                            <td class="table-cell text-right font-black text-ink-950">{{ $order->currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                                            @if ($canUpdate)
                                                <td class="table-cell">
                                                    <div class="flex justify-end gap-2">
                                                        <details>
                                                            <summary class="btn-secondary min-h-9 cursor-pointer list-none px-3 py-1.5">Editar</summary>
                                                            <div class="mt-2 w-[24rem] max-w-[80vw] rounded-2xl border border-stone-200 bg-white p-4 text-left shadow-lg">
                                                                <form method="POST" action="{{ route('orders.items.update', [$order, $item]) }}" class="space-y-3">
                                                                    @csrf @method('PUT')
                                                                    <div><label class="form-label" for="presentation-{{ $item->id }}">Presentación</label><select class="form-input" id="presentation-{{ $item->id }}" name="product_presentation_id" required>@foreach($products as $product)<optgroup label="{{ $product->name }}">@foreach($product->presentations as $presentation)<option value="{{ $presentation->id }}" @selected($presentation->id === $item->product_presentation_id)>{{ $presentation->name }}</option>@endforeach</optgroup>@endforeach</select></div>
                                                                    <div><label class="form-label" for="quantity-{{ $item->id }}">Cantidad</label><input class="form-input" id="quantity-{{ $item->id }}" name="quantity" type="number" min="0.000001" step="0.000001" required value="{{ $item->quantity }}"></div>
                                                                    @if ($canOverridePrice)
                                                                        <div><label class="form-label" for="price-{{ $item->id }}">Precio unitario</label><input class="form-input" id="price-{{ $item->id }}" name="unit_price" type="number" min="0" step="0.0001" value="{{ $item->price_source === \App\OrderPriceSource::Override ? $item->unit_price : '' }}" placeholder="Vacío recalcula"></div>
                                                                        <div><label class="form-label" for="reason-{{ $item->id }}">Motivo si cambia</label><input class="form-input" id="reason-{{ $item->id }}" name="override_reason" maxlength="1000" value="{{ $item->override_reason }}"></div>
                                                                    @endif
                                                                    <div><label class="form-label" for="notes-{{ $item->id }}">Nota</label><input class="form-input" id="notes-{{ $item->id }}" name="notes" maxlength="1000" value="{{ $item->notes }}"></div>
                                                                    <button class="btn-primary w-full" type="submit">Guardar línea</button>
                                                                </form>
                                                            </div>
                                                        </details>
                                                        <form method="POST" action="{{ route('orders.items.destroy', [$order, $item]) }}" data-confirm="¿Retirar este producto del pedido?">@csrf @method('DELETE')<button class="btn-danger" type="submit">Retirar</button></form>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-stone-50"><tr><td class="table-cell font-bold text-ink-950" colspan="3">Total del pedido</td><td class="table-cell text-right text-lg font-black text-ink-950">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>@if($canUpdate)<td></td>@endif</tr></tfoot>
                            </table>
                        </div>
                    @endif
                </section>

                @if ($canUpdate && $order->items->isNotEmpty())
                    <section class="card p-5 sm:p-7">
                        <div class="mb-5"><p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Antes de confirmar</p><h2 class="mt-1 text-lg font-black text-ink-950">Sugerencias de conversión</h2><p class="text-sm text-ink-600">Compara lo digitado con una combinación de cajas, paquetes o unidades. Nada cambia hasta que edites las líneas.</p></div>
                        <div class="space-y-4">
                            @foreach ($conversionSuggestions as $entry)
                                <article class="rounded-2xl border border-stone-200 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold text-ink-950">{{ $entry['product_name'] }}</p><p class="text-xs text-ink-600">{{ $entry['product_sku'] }}</p></div><span class="rounded-full bg-mint-100 px-3 py-1 text-xs font-bold text-leaf-700">Solo sugerencia</span></div>
                                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                                        <div><p class="text-xs font-semibold text-ink-600 uppercase">Pedido actual</p><ul class="mt-2 space-y-1 text-sm">@foreach($entry['current_lines'] as $line)<li><strong>{{ rtrim(rtrim($line['quantity'], '0'), '.') }}</strong> × {{ $line['presentation_name'] }}</li>@endforeach</ul></div>
                                        <div><p class="text-xs font-semibold text-ink-600 uppercase">Combinación sugerida</p><ul class="mt-2 space-y-1 text-sm">@forelse($entry['suggestion']['components'] as $component)<li><strong>{{ $component['count'] }}</strong> × {{ $component['name'] }} <span class="text-ink-600">({{ $order->currency }} {{ number_format((float) $component['subtotal'], 2) }})</span></li>@empty<li class="text-amber-700">No hay presentaciones activas para cubrir esta cantidad.</li>@endforelse</ul>@if(!$entry['suggestion']['is_exact'])<p class="mt-2 text-xs text-amber-700">Quedan {{ $entry['suggestion']['remaining_base_quantity'] }} {{ $entry['suggestion']['base_unit']['symbol'] }} sin cubrir.</p>@endif</div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <h3 class="font-bold text-emerald-950">Confirmar para bodega</h3>
                            <p class="mt-1 text-sm leading-6 text-emerald-900">Al confirmar, productos, cantidades y precios quedan bloqueados. Esta fase todavía no descuenta inventario.</p>
                            <form class="mt-4" method="POST" action="{{ route('orders.confirm', $order) }}" data-confirm="¿Confirmar el pedido? Después solo supervisión podrá reabrirlo.">@csrf<button class="btn-primary" type="submit">Confirmar pedido</button></form>
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-5">
                <section class="card p-5">
                    <h2 class="font-bold text-ink-950">Datos del pedido</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold text-ink-600 uppercase">Dirección guardada</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $order->customer_address }}</dd></div>
                        <div><dt class="text-xs font-semibold text-ink-600 uppercase">Entrega solicitada</dt><dd class="mt-1 font-semibold text-ink-950">{{ $order->requested_delivery_date?->format('d/m/Y') ?: 'Sin fecha definida' }}</dd></div>
                        @if($order->route_name)<div><dt class="text-xs font-semibold text-ink-600 uppercase">Visita de ruta</dt><dd class="mt-1 text-ink-800">{{ $order->route_code }} · {{ \App\Weekday::tryFrom((int) $order->route_visit_day)?->label() }} · parada #{{ $order->route_visit_order }}</dd></div>@endif
                        <div><dt class="text-xs font-semibold text-ink-600 uppercase">Registrado por</dt><dd class="mt-1 text-ink-800">{{ $order->creator->name }}</dd></div>
                        @if($order->client_reference)<div><dt class="text-xs font-semibold text-ink-600 uppercase">Referencia de sincronización</dt><dd class="mt-1 break-all font-mono text-xs text-ink-800">{{ $order->client_reference }}</dd></div>@endif
                    </dl>
                </section>

                @if ($canUpdate)
                    <section class="card p-5">
                        <h2 class="font-bold text-ink-950">Editar encabezado</h2>
                        <form method="POST" action="{{ route('orders.update', $order) }}" class="mt-4 space-y-4">
                            @csrf @method('PUT')
                            <div><label class="form-label" for="payment_term_header">Condición de pago</label><select class="form-input" id="payment_term_header" name="payment_term" required>@foreach($paymentTerms as $term)<option value="{{ $term->value }}" @selected(old('payment_term', $order->payment_term->value) === $term->value)>{{ $term->label() }}</option>@endforeach</select></div>
                            <div><label class="form-label" for="requested_delivery_date_header">Entrega solicitada</label><input class="form-input" id="requested_delivery_date_header" name="requested_delivery_date" type="date" min="{{ $order->order_date->toDateString() }}" value="{{ old('requested_delivery_date', $order->requested_delivery_date?->toDateString()) }}"></div>
                            <div><label class="form-label" for="notes_header">Notas</label><textarea class="form-input min-h-24" id="notes_header" name="notes" maxlength="2000">{{ old('notes', $order->notes) }}</textarea></div>
                            <button class="btn-secondary w-full" type="submit">Guardar datos</button>
                        </form>
                    </section>
                @elseif($order->notes)
                    <section class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Notas</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-800">{{ $order->notes }}</p></section>
                @endif

                @if ($canReopen)
                    <section class="card p-5">
                        <h2 class="font-bold text-ink-950">Reabrir pedido</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-600">Regresa el documento a borrador y deja el motivo en el historial.</p>
                        <form method="POST" action="{{ route('orders.reopen', $order) }}" class="mt-4 space-y-3" data-confirm="¿Reabrir este pedido para editarlo?">@csrf<label class="form-label" for="reopen_reason">Motivo *</label><textarea class="form-input min-h-20" id="reopen_reason" name="reason" required maxlength="1000"></textarea><button class="btn-secondary w-full" type="submit">Reabrir como borrador</button></form>
                    </section>
                @endif

                @if ($canCancel)
                    <section class="card border-red-200 p-5">
                        <h2 class="font-bold text-red-800">Cancelar pedido</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-600">No se elimina información; productos, precios e historial permanecen disponibles.</p>
                        <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-4 space-y-3" data-confirm="¿Cancelar este pedido?">@csrf<label class="form-label" for="cancel_reason">Motivo *</label><textarea class="form-input min-h-20" id="cancel_reason" name="reason" required maxlength="1000"></textarea><button class="btn-danger w-full" type="submit">Cancelar pedido</button></form>
                    </section>
                @endif

                <section class="card p-5">
                    <h2 class="font-bold text-ink-950">Historial</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ($order->statusHistory as $event)
                            <li class="border-l-2 border-stone-200 pl-3"><p class="text-sm font-semibold text-ink-950">{{ $event->to_status->label() }}</p><p class="text-xs text-ink-600">{{ $event->changedBy->name }} · {{ $event->created_at->format('d/m/Y H:i') }}</p>@if($event->reason)<p class="mt-1 whitespace-pre-line text-xs text-ink-800">{{ $event->reason }}</p>@endif</li>
                        @endforeach
                    </ol>
                </section>
            </aside>
        </div>
    </div>
@endsection
