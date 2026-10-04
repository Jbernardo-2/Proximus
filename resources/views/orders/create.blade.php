@extends('layouts.app')

@section('title', 'Nuevo pedido')
@section('page-title', 'Nuevo pedido')
@section('page-subtitle', 'Primero crea el borrador; después agrega productos y revisa las conversiones.')
@section('header-actions')<a href="{{ route('orders.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <form method="POST" action="{{ route('orders.store') }}" class="space-y-6" data-order-create-form>
        @csrf
        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Paso 1 de 2</p>
                <h2 class="mt-1 text-lg font-black text-ink-950">Cliente y ruta</h2>
                <p class="text-sm text-ink-600">El preventista solo puede tomar pedidos de sus visitas asignadas. Supervisión también puede registrar pedidos fuera de ruta.</p>
            </div>
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="lg:col-span-2">
                    <label class="form-label" for="route_stop_id">Visita programada {{ $isPreventista ? '*' : '(opcional)' }}</label>
                    <select class="form-input" id="route_stop_id" name="route_stop_id" @required($isPreventista) data-route-stop-select>
                        <option value="">{{ $isPreventista ? 'Selecciona una visita…' : 'Pedido fuera de ruta' }}</option>
                        @foreach ($routeStops as $stop)
                            <option value="{{ $stop->id }}" data-customer-id="{{ $stop->customer_id }}" data-salesperson-id="{{ $stop->salesRoute->salesperson_id }}" @selected(old('route_stop_id', $selectedRouteStop?->id) === $stop->id)>
                                {{ $stop->visit_day->label() }} #{{ $stop->visit_order }} · {{ $stop->salesRoute->name }} · {{ $stop->customer->business_name }}
                            </option>
                        @endforeach
                    </select>
                    @if ($routeStops->isEmpty())
                        <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">No hay visitas activas disponibles. Configura la ruta o solicita a supervisión que revise la asignación.</p>
                    @else
                        <p class="form-help">Elegir una visita completa automáticamente el cliente y el preventista.</p>
                    @endif
                </div>
                <div>
                    <label class="form-label" for="customer_id">Cliente *</label>
                    <select class="form-input" id="customer_id" name="customer_id" required data-order-customer-select>
                        <option value="">Selecciona…</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $selectedRouteStop?->customer_id) === $customer->id)>{{ $customer->business_name }} · {{ $customer->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salesperson_id">Preventista responsable *</label>
                    <select class="form-input" id="salesperson_id" name="salesperson_id" required data-order-salesperson-select>
                        <option value="">Selecciona…</option>
                        @foreach ($salespeople as $salesperson)
                            <option value="{{ $salesperson->id }}" @selected((string) old('salesperson_id', $selectedRouteStop?->salesRoute?->salesperson_id ?? ($isPreventista ? auth()->id() : '')) === (string) $salesperson->id)>{{ $salesperson->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6"><h2 class="text-lg font-black text-ink-950">Condiciones del pedido</h2><p class="text-sm text-ink-600">Este documento es interno y no genera una factura fiscal.</p></div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="form-label" for="warehouse_id">Bodega que preparará *</label>
                    <select class="form-input" id="warehouse_id" name="warehouse_id" required>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $warehouses->firstWhere('is_default', true)?->id ?? $warehouses->first()?->id) === $warehouse->id)>{{ $warehouse->name }} · {{ $warehouse->code }}</option>
                        @endforeach
                    </select>
                    @if ($warehouses->isEmpty())<p class="form-help text-red-700">No hay bodegas activas. Solicita a supervisión que configure una.</p>@endif
                </div>
                <div><label class="form-label" for="order_date">Fecha del pedido *</label><input class="form-input" id="order_date" name="order_date" type="date" max="{{ now()->toDateString() }}" required value="{{ old('order_date', now()->toDateString()) }}"></div>
                <div><label class="form-label" for="requested_delivery_date">Entrega solicitada</label><input class="form-input" id="requested_delivery_date" name="requested_delivery_date" type="date" value="{{ old('requested_delivery_date') }}"></div>
                <div><label class="form-label" for="payment_term">Condición de pago *</label><select class="form-input" id="payment_term" name="payment_term" required>@foreach($paymentTerms as $term)<option value="{{ $term->value }}" @selected(old('payment_term', \App\PaymentTerm::Cash->value) === $term->value)>{{ $term->label() }}</option>@endforeach</select></div>
                <div class="sm:col-span-2 xl:col-span-3"><label class="form-label" for="notes">Notas del pedido</label><textarea class="form-input min-h-24" id="notes" name="notes" maxlength="2000" placeholder="Indicaciones de entrega, horario o referencia interna">{{ old('notes') }}</textarea></div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('orders.index') }}">Cancelar</a>
            <button class="btn-primary" @disabled(($routeStops->isEmpty() && $isPreventista) || $warehouses->isEmpty())>Crear borrador y agregar productos →</button>
        </div>
    </form>
@endsection
