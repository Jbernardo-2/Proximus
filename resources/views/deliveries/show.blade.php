@extends('layouts.app')

@section('title', $deliveryRun->run_number)
@section('page-title', $deliveryRun->run_number)
@section('page-subtitle', $deliveryRun->scheduled_date->format('d/m/Y').' · '.$deliveryRun->driver_name.' · '.$deliveryRun->warehouse_name)
@section('header-actions')
    <div class="flex gap-2">@if($canUpdate)<a href="{{ route('delivery-runs.edit', $deliveryRun) }}" class="btn-secondary">Editar</a>@endif<a href="{{ route('delivery-runs.index') }}" class="btn-secondary">Volver</a></div>
@endsection

@section('content')
    @php
        $quantity = static fn ($value): string => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
        $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
        $statusSteps = [
            \App\DeliveryRunStatus::Draft,
            \App\DeliveryRunStatus::Preparing,
            \App\DeliveryRunStatus::Loaded,
            \App\DeliveryRunStatus::InTransit,
            \App\DeliveryRunStatus::AwaitingSettlement,
            \App\DeliveryRunStatus::Settled,
        ];
        $currentStep = array_search($deliveryRun->status, $statusSteps, true);
        $preparedOrdersCount = $deliveryRun->runOrders->where('status', \App\DeliveryOrderStatus::Prepared)->count();
        $pendingPreparationCount = $deliveryRun->runOrders->count() - $preparedOrdersCount;
        $allOrdersPrepared = $deliveryRun->runOrders->isNotEmpty() && $pendingPreparationCount === 0;
    @endphp

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-5"><p class="text-xs font-semibold tracking-wide text-ink-600 uppercase">Estado</p><div class="mt-3"><x-delivery-run-status-badge :status="$deliveryRun->status" /></div><p class="mt-2 text-xs text-ink-600">{{ $deliveryRun->run_orders_count }} pedidos asignados</p></article>
            <article class="card p-5"><p class="text-xs font-semibold tracking-wide text-ink-600 uppercase">Repartidor</p><p class="mt-2 font-black text-ink-950">{{ $deliveryRun->driver_name }}</p><p class="text-xs text-ink-600">{{ $deliveryRun->vehicle_license_plate ?: ($deliveryRun->vehicle_code ?: 'Sin vehículo definido') }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold tracking-wide text-ink-600 uppercase">Venta entregada</p><p class="mt-1 text-2xl font-black text-ink-950">{{ $currency }} {{ number_format((float) $deliveryRun->delivered_total, 2) }}</p><p class="text-xs text-ink-600">Cobrado {{ $currency }} {{ number_format((float) $deliveryRun->collected_total, 2) }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold tracking-wide text-ink-600 uppercase">Saldo pendiente</p><p class="mt-1 text-2xl font-black {{ bccomp($deliveryRun->credit_total, '0', 4) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $currency }} {{ number_format((float) $deliveryRun->credit_total, 2) }}</p><p class="text-xs text-ink-600">Facturación interna, no fiscal</p></article>
        </section>

        @if($deliveryRun->status === \App\DeliveryRunStatus::Cancelled)
            <section class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-950"><p class="font-bold">Jornada cancelada</p><p class="mt-1 whitespace-pre-line text-sm">{{ $deliveryRun->cancellation_reason }}</p></section>
        @else
            <section class="card overflow-hidden p-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    @foreach($statusSteps as $index => $step)
                        @php($reached = $currentStep !== false && $index <= $currentStep)
                        <div class="rounded-xl border {{ $reached ? 'border-leaf-200 bg-mint-50' : 'border-stone-200 bg-stone-50' }} p-3"><p class="text-[11px] font-bold {{ $reached ? 'text-leaf-700' : 'text-stone-500' }} uppercase">{{ $index + 1 }}</p><p class="mt-1 text-sm font-semibold {{ $reached ? 'text-ink-950' : 'text-stone-500' }}">{{ $step->label() }}</p></div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                @if($canAssign)
                    <section class="card p-5 sm:p-7">
                        <div class="mb-5"><p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Planificación</p><h2 class="mt-1 text-lg font-black text-ink-950">Asignar pedido confirmado</h2><p class="text-sm text-ink-600">Puedes combinar pedidos de varias rutas; todos deben salir de {{ $deliveryRun->warehouse_name }}.</p></div>
                        @if($candidateOrders->isEmpty())
                            <x-empty-state title="Sin pedidos disponibles" description="No hay pedidos confirmados de esta bodega pendientes de asignación." />
                        @else
                            <form method="POST" action="{{ route('delivery-runs.orders.store', $deliveryRun) }}" class="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_8rem_auto]">
                                @csrf
                                <div><label class="form-label" for="order_id">Pedido *</label><select class="form-input" id="order_id" name="order_id" required><option value="">Selecciona…</option>@foreach($candidateOrders as $order)<option value="{{ $order->id }}">{{ $order->order_number }} · {{ $order->customer_name }} · {{ $order->route_name ?: 'Fuera de ruta' }} · {{ $currency }} {{ number_format((float) $order->total, 2) }}</option>@endforeach</select></div>
                                <div><label class="form-label" for="visit_order">Parada</label><input class="form-input" id="visit_order" name="visit_order" type="number" min="1" max="65535" value="{{ old('visit_order', $deliveryRun->runOrders->count() + 1) }}"></div>
                                <div class="flex items-end"><button class="btn-primary w-full" type="submit">Asignar</button></div>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h2 class="font-bold text-ink-950">Pedidos de la jornada</h2><p class="text-sm text-ink-600">Orden de visita, cliente, ruta y condición de pago.</p></div><span class="text-sm font-bold text-ink-950">{{ $deliveryRun->runOrders->count() }} pedidos</span></div>
                    @if($deliveryRun->runOrders->isEmpty())
                        <div class="p-6"><x-empty-state title="Jornada vacía" description="Asigna pedidos confirmados antes de iniciar la preparación." /></div>
                    @else
                        <div class="overflow-x-auto"><table class="w-full min-w-[920px]"><thead class="bg-stone-50"><tr><th class="table-heading">Parada</th><th class="table-heading">Pedido y cliente</th><th class="table-heading">Ruta</th><th class="table-heading">Pago</th><th class="table-heading text-right">Solicitado</th><th class="table-heading">Estado</th>@if($canAssign)<th class="table-heading text-right">Acción</th>@endif</tr></thead><tbody>
                            @foreach($deliveryRun->runOrders as $runOrder)
                                <tr><td class="table-cell text-lg font-black">#{{ $runOrder->visit_order }}</td><td class="table-cell"><a class="font-bold text-leaf-700 hover:underline" href="{{ route('orders.show', $runOrder->order) }}">{{ $runOrder->order->order_number }}</a><p class="text-sm font-semibold text-ink-950">{{ $runOrder->order->customer_name }}</p><p class="max-w-sm truncate text-xs text-ink-600">{{ $runOrder->order->customer_address }}</p></td><td class="table-cell"><p class="font-semibold">{{ $runOrder->order->route_name ?: 'Fuera de ruta' }}</p><p class="text-xs text-ink-600">{{ $runOrder->order->salesperson_name }}</p></td><td class="table-cell">{{ $runOrder->order->payment_term->label() }}</td><td class="table-cell text-right font-black">{{ $currency }} {{ number_format((float) $runOrder->requested_total, 2) }}</td><td class="table-cell"><x-delivery-order-status-badge :status="$runOrder->status" /></td>@if($canAssign)<td class="table-cell text-right"><form method="POST" action="{{ route('delivery-runs.orders.destroy', [$deliveryRun, $runOrder]) }}" data-confirm="¿Retirar este pedido de la jornada? La reserva seguirá activa.">@csrf @method('DELETE')<button class="btn-danger" type="submit">Retirar</button></form></td>@endif</tr>
                            @endforeach
                        </tbody></table></div>
                    @endif
                </section>

                @if($deliveryRun->runOrders->isNotEmpty())
                    <section class="card overflow-hidden">
                        <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-bold text-ink-950">Resumen consolidado de carga</h2><p class="text-sm text-ink-600">Bodega prepara por presentación sin perder la asignación de cada pedido.</p></div>
                        <div class="overflow-x-auto"><table class="w-full min-w-[760px]"><thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading text-right">Solicitado</th><th class="table-heading text-right">Preparado</th><th class="table-heading text-right">Cargado</th><th class="table-heading text-right">Físico en bodega</th></tr></thead><tbody>
                            @foreach($pickingSummary as $summary)
                                @php($stock = $inventoryStocks->get($summary['product_id']))
                                <tr><td class="table-cell"><p class="font-semibold text-ink-950">{{ $summary['product_name'] }}</p><p class="text-xs text-ink-600">{{ $summary['product_sku'] }} · {{ $summary['presentation_name'] }}</p></td><td class="table-cell text-right font-bold">{{ $quantity($summary['requested_quantity']) }}</td><td class="table-cell text-right font-bold text-amber-700">{{ $quantity($summary['prepared_quantity']) }}</td><td class="table-cell text-right font-bold text-indigo-700">{{ $quantity($summary['loaded_quantity']) }}</td><td class="table-cell text-right"><p class="font-bold">{{ $quantity($stock?->quantity_on_hand ?? 0) }} {{ $summary['base_unit_symbol'] }}</p><p class="text-xs text-ink-600">Disponible {{ $quantity($stock?->availableQuantity() ?? 0) }}</p></td></tr>
                            @endforeach
                        </tbody></table></div>
                    </section>
                @endif

                @if($canStartPreparation)
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6"><h2 class="font-black text-amber-950">Iniciar preparación de bodega</h2><p class="mt-1 text-sm leading-6 text-amber-900">Al iniciar se bloquea la planificación. Después registrarás la cantidad realmente preparada de cada línea.</p><form class="mt-4" method="POST" action="{{ route('delivery-runs.preparation.start', $deliveryRun) }}" data-confirm="¿Iniciar la preparación? Ya no podrás agregar ni retirar pedidos.">@csrf<button class="btn-primary" type="submit">Iniciar preparación</button></form></section>
                @endif

                @if($canPrepare)
                    <section class="card overflow-hidden" id="preparation-queue">
                        <header class="border-b border-stone-100 p-5 sm:p-7">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div><p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Bodega · cola de preparación</p><h2 class="mt-1 text-xl font-black text-ink-950">Guarda un pedido a la vez</h2><p class="mt-1 text-sm text-ink-600">Cada pedido conserva su avance. El inventario se descuenta una sola vez al confirmar la carga completa.</p></div>
                                <div class="rounded-2xl {{ $allOrdersPrepared ? 'bg-emerald-100 text-emerald-950' : 'bg-amber-100 text-amber-950' }} px-5 py-3 text-center"><p class="text-2xl font-black">{{ $preparedOrdersCount }}/{{ $deliveryRun->runOrders->count() }}</p><p class="text-xs font-bold uppercase">pedidos listos</p></div>
                            </div>
                            <progress class="mt-4 h-2 w-full overflow-hidden rounded-full accent-leaf-600" value="{{ $preparedOrdersCount }}" max="{{ max($deliveryRun->runOrders->count(), 1) }}">{{ $preparedOrdersCount }} de {{ $deliveryRun->runOrders->count() }}</progress>
                        </header>
                        <div class="divide-y divide-stone-100">
                            @foreach($deliveryRun->runOrders as $runOrder)
                                @php($prepared = $runOrder->status === \App\DeliveryOrderStatus::Prepared)
                                <article class="grid items-center gap-4 p-5 lg:grid-cols-[5rem_minmax(0,1fr)_auto] {{ $prepared ? 'bg-emerald-50/50' : '' }}">
                                    <div class="grid size-14 place-items-center rounded-2xl {{ $prepared ? 'bg-emerald-600 text-white' : 'bg-amber-100 text-amber-950' }} text-xl font-black">#{{ $runOrder->visit_order }}</div>
                                    <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-black text-ink-950">{{ $runOrder->order->customer_name }}</h3><x-delivery-order-status-badge :status="$runOrder->status" /></div><p class="text-sm text-ink-600">{{ $runOrder->order->order_number }} · {{ $runOrder->items->count() }} líneas · {{ $currency }} {{ number_format((float) $runOrder->requested_total, 2) }}</p>@if($prepared)<p class="mt-1 text-xs font-semibold text-emerald-700">Guardado {{ $runOrder->prepared_at?->format('d/m/Y H:i') }} por {{ $runOrder->preparedBy?->name ?: 'el equipo de bodega' }}</p>@else<p class="mt-1 text-xs font-semibold text-amber-700">Pendiente de registrar cantidades</p>@endif</div>
                                    <a class="{{ $prepared ? 'btn-secondary' : 'btn-primary' }}" href="{{ route('delivery-runs.orders.preparation.edit', [$deliveryRun, $runOrder]) }}">{{ $prepared ? 'Revisar preparación' : 'Preparar pedido' }}</a>
                                </article>
                            @endforeach
                        </div>
                        @if($canLoad)
                            @if($allOrdersPrepared)
                                <div class="border-t border-indigo-200 bg-indigo-50 p-5 sm:p-6"><h3 class="font-black text-indigo-950">Todos los pedidos están preparados</h3><p class="mt-1 text-sm leading-6 text-indigo-900">Al confirmar, la salida de todos los productos se ejecutará dentro de una sola transacción. Si una existencia falla, no se descontará nada.</p><form class="mt-4" method="POST" action="{{ route('delivery-runs.load', $deliveryRun) }}" data-confirm="¿Confirmar la carga completa? Las cantidades dejarán de ser editables.">@csrf<button class="btn-primary" type="submit">Confirmar carga completa</button></form></div>
                            @else
                                <div class="border-t border-amber-200 bg-amber-50 p-5"><p class="font-bold text-amber-950">Faltan {{ $pendingPreparationCount }} {{ $pendingPreparationCount === 1 ? 'pedido' : 'pedidos' }}</p><p class="text-sm text-amber-900">Cuando todos estén preparados aparecerá el botón para confirmar la carga e iniciar la ruta.</p></div>
                            @endif
                        @endif
                    </section>
                @endif

                @if($canDepart)
                    <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 sm:p-6"><h2 class="font-black text-indigo-950">Carga lista para salir</h2><p class="mt-1 text-sm leading-6 text-indigo-900">Confirma la salida para habilitar entregas y cobros del repartidor.</p><form class="mt-4" method="POST" action="{{ route('delivery-runs.depart', $deliveryRun) }}" data-confirm="¿Confirmar que el repartidor inició la ruta?"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Iniciar ruta</button></form></section>
                @endif

                @if(in_array($deliveryRun->status, [\App\DeliveryRunStatus::InTransit, \App\DeliveryRunStatus::AwaitingSettlement, \App\DeliveryRunStatus::Settled], true))
                    <section class="space-y-5">
                        <div><p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Paradas</p><h2 class="mt-1 text-xl font-black text-ink-950">Entregas y cobros</h2><p class="text-sm text-ink-600">Toda la carga debe terminar entregada, devuelta, dañada o faltante.</p></div>
                        @foreach($deliveryRun->runOrders as $runOrder)
                            <article class="card overflow-hidden" id="stop-{{ $runOrder->id }}">
                                <header class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-100 px-5 py-4"><div><p class="text-xs font-bold text-leaf-700 uppercase">Parada #{{ $runOrder->visit_order }}</p><h3 class="mt-1 text-lg font-black text-ink-950">{{ $runOrder->order->customer_name }}</h3><p class="text-sm text-ink-600">{{ $runOrder->order->order_number }} · {{ $runOrder->order->route_name ?: 'Fuera de ruta' }} · {{ $runOrder->order->payment_term->label() }}</p></div><div class="text-right"><x-delivery-order-status-badge :status="$runOrder->status" /><p class="mt-2 text-sm font-black text-ink-950">{{ $currency }} {{ number_format((float) $runOrder->delivered_total, 2) }}</p></div></header>

                                @if($canExecute)
                                    <form method="POST" action="{{ route('delivery-runs.orders.outcome', [$deliveryRun, $runOrder]) }}" class="space-y-5 p-5">
                                        @csrf @method('PUT')
                                        <div class="overflow-x-auto"><table class="w-full min-w-[900px]"><thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading text-right">Cargado</th><th class="table-heading">Entregado</th><th class="table-heading">Devuelto</th><th class="table-heading">Dañado</th><th class="table-heading">Faltante</th></tr></thead><tbody>
                                            @foreach($runOrder->items as $item)
                                                @php($neverCompleted = $runOrder->completed_at === null)
                                                <tr><td class="table-cell"><p class="font-semibold text-ink-950">{{ $item->product_name }}</p><p class="text-xs text-ink-600">{{ $item->presentation_name }} · {{ $currency }} {{ number_format((float) $item->unit_price, 2) }}</p><input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}"></td><td class="table-cell text-right font-black">{{ $quantity($item->loaded_quantity) }}</td><td class="table-cell"><input class="form-input min-h-9 w-28 py-1.5" aria-label="Entregado de {{ $item->product_name }}" name="items[{{ $item->id }}][delivered_quantity]" type="number" min="0" max="{{ $item->loaded_quantity }}" step="0.000001" required value="{{ old("items.{$item->id}.delivered_quantity", $neverCompleted ? $item->loaded_quantity : $item->delivered_quantity) }}"></td><td class="table-cell"><input class="form-input min-h-9 w-28 py-1.5" aria-label="Devuelto de {{ $item->product_name }}" name="items[{{ $item->id }}][returned_quantity]" type="number" min="0" max="{{ $item->loaded_quantity }}" step="0.000001" required value="{{ old("items.{$item->id}.returned_quantity", $item->returned_quantity) }}"></td><td class="table-cell"><input class="form-input min-h-9 w-28 py-1.5" aria-label="Dañado de {{ $item->product_name }}" name="items[{{ $item->id }}][damaged_quantity]" type="number" min="0" max="{{ $item->loaded_quantity }}" step="0.000001" required value="{{ old("items.{$item->id}.damaged_quantity", $item->damaged_quantity) }}"></td><td class="table-cell"><input class="form-input min-h-9 w-28 py-1.5" aria-label="Faltante de {{ $item->product_name }}" name="items[{{ $item->id }}][missing_quantity]" type="number" min="0" max="{{ $item->loaded_quantity }}" step="0.000001" required value="{{ old("items.{$item->id}.missing_quantity", $item->missing_quantity) }}"></td></tr>
                                            @endforeach
                                        </tbody></table></div>
                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                            <div><label class="form-label" for="receiver-{{ $runOrder->id }}">Persona que recibió</label><input class="form-input" id="receiver-{{ $runOrder->id }}" name="receiver_name" maxlength="160" value="{{ old('receiver_name', $runOrder->receiver_name) }}" placeholder="Obligatorio si se entrega"></div>
                                            <div><label class="form-label" for="reason-{{ $runOrder->id }}">Motivo si no fue completa</label><select class="form-input" id="reason-{{ $runOrder->id }}" name="outcome_reason"><option value="">Entrega completa</option>@foreach($outcomeReasons as $reason)<option value="{{ $reason->value }}" @selected(old('outcome_reason', $runOrder->outcome_reason?->value) === $reason->value)>{{ $reason->label() }}</option>@endforeach</select></div>
                                            <div><label class="form-label" for="credit-{{ $runOrder->id }}">Justificación de saldo</label><input class="form-input" id="credit-{{ $runOrder->id }}" name="credit_reason" maxlength="2000" value="{{ old('credit_reason', $runOrder->credit_reason) }}" placeholder="Si un pedido de contado queda pendiente"></div>
                                            <div class="sm:col-span-2"><label class="form-label" for="notes-{{ $runOrder->id }}">Observaciones</label><textarea class="form-input min-h-20" id="notes-{{ $runOrder->id }}" name="outcome_notes" maxlength="2000">{{ old('outcome_notes', $runOrder->outcome_notes) }}</textarea></div>
                                            <div class="grid grid-cols-2 gap-3"><div><label class="form-label" for="lat-{{ $runOrder->id }}">Latitud</label><input class="form-input" id="lat-{{ $runOrder->id }}" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $runOrder->latitude) }}"></div><div><label class="form-label" for="lng-{{ $runOrder->id }}">Longitud</label><input class="form-input" id="lng-{{ $runOrder->id }}" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $runOrder->longitude) }}"></div></div>
                                        </div>
                                        <div class="flex justify-end"><button class="btn-primary" type="submit">Guardar resultado</button></div>
                                    </form>
                                @else
                                    <div class="overflow-x-auto p-5"><table class="w-full min-w-[760px]"><thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading text-right">Cargado</th><th class="table-heading text-right">Entregado</th><th class="table-heading text-right">Devuelto</th><th class="table-heading text-right">Dañado</th><th class="table-heading text-right">Faltante</th></tr></thead><tbody>@foreach($runOrder->items as $item)<tr><td class="table-cell"><p class="font-semibold">{{ $item->product_name }}</p><p class="text-xs text-ink-600">{{ $item->presentation_name }}</p></td><td class="table-cell text-right">{{ $quantity($item->loaded_quantity) }}</td><td class="table-cell text-right font-bold text-emerald-700">{{ $quantity($item->delivered_quantity) }}</td><td class="table-cell text-right">{{ $quantity($item->returned_quantity) }}</td><td class="table-cell text-right text-red-700">{{ $quantity($item->damaged_quantity) }}</td><td class="table-cell text-right text-red-700">{{ $quantity($item->missing_quantity) }}</td></tr>@endforeach</tbody></table></div>
                                @endif

                                @if($runOrder->status->isCompleted())
                                    <div class="border-t border-stone-100 bg-stone-50 p-5">
                                        <div class="grid gap-4 sm:grid-cols-3"><div><p class="text-xs font-semibold text-ink-600 uppercase">Entregado</p><p class="mt-1 text-xl font-black">{{ $currency }} {{ number_format((float) $runOrder->delivered_total, 2) }}</p></div><div><p class="text-xs font-semibold text-ink-600 uppercase">Cobrado</p><p class="mt-1 text-xl font-black text-emerald-700">{{ $currency }} {{ number_format((float) $runOrder->collected_total, 2) }}</p></div><div><p class="text-xs font-semibold text-ink-600 uppercase">Saldo</p><p class="mt-1 text-xl font-black {{ bccomp($runOrder->balance_due, '0', 4) > 0 ? 'text-amber-700' : 'text-ink-950' }}">{{ $currency }} {{ number_format((float) $runOrder->balance_due, 2) }}</p></div></div>
                                        @if($runOrder->outcome_reason)<p class="mt-3 text-sm text-ink-700"><strong>{{ $runOrder->outcome_reason->label() }}.</strong> {{ $runOrder->outcome_notes }}</p>@endif
                                        <div class="mt-5 space-y-3">
                                            @forelse($runOrder->payments as $payment)
                                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white p-3"><div><p class="font-semibold text-ink-950">{{ $payment->receipt_number }} · {{ $payment->method->label() }}</p><p class="text-xs text-ink-600">{{ $payment->received_at->format('d/m/Y H:i') }} · {{ $payment->receivedBy->name }}@if($payment->reference) · Ref. {{ $payment->reference }}@endif</p>@if($payment->status === \App\DeliveryPaymentStatus::Voided)<p class="mt-1 text-xs font-bold text-red-700">Anulado: {{ $payment->void_reason }}</p>@endif</div><div class="flex items-center gap-3"><p class="font-black {{ $payment->status === \App\DeliveryPaymentStatus::Voided ? 'text-stone-400 line-through' : 'text-emerald-700' }}">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</p>@if($canVoidPayments && $payment->status === \App\DeliveryPaymentStatus::Active)<details><summary class="btn-danger min-h-8 cursor-pointer list-none px-3 py-1">Anular</summary><form method="POST" action="{{ route('delivery-runs.orders.payments.void', [$deliveryRun, $runOrder, $payment]) }}" class="mt-2 w-72 space-y-2 rounded-xl border border-red-200 bg-white p-3 shadow-lg">@csrf<label class="form-label" for="void-{{ $payment->id }}">Motivo *</label><textarea class="form-input min-h-20" id="void-{{ $payment->id }}" name="reason" required maxlength="1000"></textarea><button class="btn-danger w-full" type="submit">Confirmar anulación</button></form></details>@endif</div></div>
                                            @empty
                                                <p class="text-sm text-ink-600">Aún no hay cobros registrados.</p>
                                            @endforelse
                                        </div>
                                        @if($canExecute && bccomp($runOrder->balance_due, '0', 4) > 0)
                                            <form method="POST" action="{{ route('delivery-runs.orders.payments.store', [$deliveryRun, $runOrder]) }}" class="mt-5 grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 lg:grid-cols-[12rem_9rem_minmax(10rem,1fr)_auto]" data-payment-form>
                                                @csrf
                                                <div><label class="form-label" for="method-{{ $runOrder->id }}">Método *</label><select class="form-input" id="method-{{ $runOrder->id }}" name="method" required data-payment-method>@foreach($paymentMethods as $method)<option value="{{ $method->value }}" data-requires-reference="{{ $method->requiresReference() ? 'true' : 'false' }}">{{ $method->label() }}</option>@endforeach</select></div>
                                                <div><label class="form-label" for="amount-{{ $runOrder->id }}">Monto *</label><input class="form-input" id="amount-{{ $runOrder->id }}" name="amount" type="number" min="0.0001" max="{{ $runOrder->balance_due }}" step="0.0001" required value="{{ $runOrder->balance_due }}"></div>
                                                <div><label class="form-label" for="reference-{{ $runOrder->id }}">Referencia <span data-reference-required-label></span></label><input class="form-input" id="reference-{{ $runOrder->id }}" name="reference" maxlength="120" placeholder="Número de transferencia, cheque o autorización" data-payment-reference></div>
                                                <div class="flex items-end"><button class="btn-primary w-full" type="submit">Registrar cobro</button></div>
                                            </form>
                                        @endif
                                        @if($deliveryRun->status === \App\DeliveryRunStatus::Settled && $runOrder->status === \App\DeliveryOrderStatus::NotDelivered && auth()->user()->canManageOrderLifecycle())
                                            <form method="POST" action="{{ route('delivery-runs.orders.requeue', [$deliveryRun, $runOrder]) }}" class="mt-5 flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:flex-row sm:items-end">@csrf<div class="flex-1"><label class="form-label" for="requeue-{{ $runOrder->id }}">Motivo para reprogramar *</label><input class="form-input" id="requeue-{{ $runOrder->id }}" name="reason" required maxlength="1000" placeholder="Nueva fecha acordada, cliente solicitó reintento…"></div><button class="btn-secondary" type="submit">Reprogramar pedido</button></form>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </section>
                @endif

                @if($canSettle)
                    <section class="card p-5 sm:p-7">
                        <div class="mb-5"><p class="text-xs font-semibold tracking-[0.14em] text-violet-700 uppercase">Cierre de jornada</p><h2 class="mt-1 text-xl font-black text-ink-950">Liquidación de ruta</h2><p class="text-sm text-ink-600">Al cerrar se reingresan las devoluciones a bodega y el resultado se vuelve inmutable.</p></div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-semibold text-ink-600 uppercase">Efectivo esperado</p><p class="mt-1 text-xl font-black">{{ $currency }} {{ number_format((float) $deliveryRun->cash_expected, 2) }}</p></div><div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-semibold text-ink-600 uppercase">Transferencias</p><p class="mt-1 text-xl font-black">{{ $currency }} {{ number_format((float) $deliveryRun->transfer_total, 2) }}</p></div><div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-semibold text-ink-600 uppercase">Otros cobros</p><p class="mt-1 text-xl font-black">{{ $currency }} {{ number_format((float) bcadd(bcadd($deliveryRun->card_total, $deliveryRun->check_total, 4), $deliveryRun->other_payment_total, 4), 2) }}</p></div><div class="rounded-xl bg-amber-50 p-4"><p class="text-xs font-semibold text-amber-800 uppercase">Crédito / saldo</p><p class="mt-1 text-xl font-black text-amber-900">{{ $currency }} {{ number_format((float) $deliveryRun->credit_total, 2) }}</p></div></div>
                        <form method="POST" action="{{ route('delivery-runs.settle', $deliveryRun) }}" class="mt-6 space-y-4" data-confirm="¿Liquidar definitivamente la jornada? Los resultados ya no podrán editarse.">@csrf<div><label class="form-label" for="cash_declared">Efectivo entregado por el repartidor *</label><input class="form-input max-w-xs" id="cash_declared" name="cash_declared" type="number" min="0" step="0.0001" required value="{{ old('cash_declared', $deliveryRun->cash_expected) }}"></div><div><label class="form-label" for="settlement_notes">Observaciones de liquidación</label><textarea class="form-input min-h-24" id="settlement_notes" name="settlement_notes" maxlength="3000" placeholder="Obligatorio cuando exista una diferencia de efectivo">{{ old('settlement_notes') }}</textarea></div><div class="flex justify-end"><button class="btn-primary" type="submit">Liquidar jornada</button></div></form>
                    </section>
                @endif

                @if($deliveryRun->status === \App\DeliveryRunStatus::Settled)
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6"><h2 class="font-black text-emerald-950">Jornada liquidada</h2><div class="mt-4 grid gap-4 sm:grid-cols-3"><div><p class="text-xs font-semibold text-emerald-800 uppercase">Efectivo esperado</p><p class="text-xl font-black">{{ $currency }} {{ number_format((float) $deliveryRun->cash_expected, 2) }}</p></div><div><p class="text-xs font-semibold text-emerald-800 uppercase">Efectivo declarado</p><p class="text-xl font-black">{{ $currency }} {{ number_format((float) $deliveryRun->cash_declared, 2) }}</p></div><div><p class="text-xs font-semibold text-emerald-800 uppercase">Diferencia</p><p class="text-xl font-black {{ bccomp($deliveryRun->cash_difference, '0', 4) === 0 ? 'text-emerald-900' : 'text-red-700' }}">{{ $currency }} {{ number_format((float) $deliveryRun->cash_difference, 2) }}</p></div></div>@if($deliveryRun->settlement_notes)<p class="mt-4 whitespace-pre-line text-sm text-emerald-950">{{ $deliveryRun->settlement_notes }}</p>@endif</section>
                @endif
            </div>

            <aside class="space-y-5">
                <section class="card p-5"><h2 class="font-bold text-ink-950">Datos de operación</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs font-semibold text-ink-600 uppercase">Bodega</dt><dd class="mt-1 font-semibold">{{ $deliveryRun->warehouse_name }}</dd><dd class="text-xs text-ink-600">{{ $deliveryRun->warehouse_code }}</dd></div><div><dt class="text-xs font-semibold text-ink-600 uppercase">Fecha</dt><dd class="mt-1 font-semibold">{{ $deliveryRun->scheduled_date->format('d/m/Y') }}</dd></div><div><dt class="text-xs font-semibold text-ink-600 uppercase">Vehículo</dt><dd class="mt-1 font-semibold">{{ $deliveryRun->vehicle_code ?: 'No definido' }}</dd><dd class="text-xs text-ink-600">{{ $deliveryRun->vehicle_license_plate }}</dd></div>@if($deliveryRun->notes)<div><dt class="text-xs font-semibold text-ink-600 uppercase">Notas</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $deliveryRun->notes }}</dd></div>@endif</dl></section>

                <section class="card p-5"><h2 class="font-bold text-ink-950">Trazabilidad</h2><ol class="mt-4 space-y-4">@foreach($deliveryRun->statusHistory as $event)<li class="border-l-2 border-stone-200 pl-3"><p class="text-sm font-semibold text-ink-950">{{ $event->to_status->label() }}</p><p class="text-xs text-ink-600">{{ $event->changedBy->name }} · {{ $event->created_at->format('d/m/Y H:i') }}</p>@if($event->reason)<p class="mt-1 whitespace-pre-line text-xs text-ink-800">{{ $event->reason }}</p>@endif</li>@endforeach</ol></section>

                @if($canCancel)
                    <section class="card border-red-200 p-5"><h2 class="font-bold text-red-800">Cancelar jornada</h2><p class="mt-2 text-sm leading-6 text-ink-600">Los pedidos volverán a confirmado y conservarán sus reservas. Solo es posible antes de cargar.</p><form method="POST" action="{{ route('delivery-runs.cancel', $deliveryRun) }}" class="mt-4 space-y-3" data-confirm="¿Cancelar esta jornada?">@csrf<label class="form-label" for="cancel_reason">Motivo *</label><textarea class="form-input min-h-20" id="cancel_reason" name="reason" required maxlength="1000"></textarea><button class="btn-danger w-full" type="submit">Cancelar jornada</button></form></section>
                @endif
            </aside>
        </div>
    </div>
@endsection
