@extends('layouts.app')

@php($editing = $deliveryRun->exists)
@section('title', $editing ? 'Editar jornada' : 'Nueva jornada')
@section('page-title', $editing ? 'Editar jornada' : 'Nueva jornada de reparto')
@section('page-subtitle', 'Define la bodega, fecha, repartidor y vehículo antes de asignar pedidos.')
@section('header-actions')<a href="{{ $editing ? route('delivery-runs.show', $deliveryRun) : route('delivery-runs.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('delivery-runs.update', $deliveryRun) : route('delivery-runs.store') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <section class="card p-5 sm:p-7">
            <div class="mb-6"><p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Planificación</p><h2 class="mt-1 text-xl font-black text-ink-950">Datos de la jornada</h2><p class="mt-1 text-sm text-ink-600">Una jornada puede llevar pedidos de varias rutas, siempre que salgan de la misma bodega.</p></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label class="form-label" for="scheduled_date">Fecha programada *</label><input class="form-input" id="scheduled_date" name="scheduled_date" type="date" required value="{{ old('scheduled_date', $deliveryRun->scheduled_date?->format('Y-m-d')) }}"></div>
                <div><label class="form-label" for="warehouse_id">Bodega *</label><select class="form-input" id="warehouse_id" name="warehouse_id" required><option value="">Selecciona…</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $deliveryRun->warehouse_id) === $warehouse->id)>{{ $warehouse->name }} · {{ $warehouse->code }}</option>@endforeach</select></div>
                <div><label class="form-label" for="driver_id">Repartidor *</label><select class="form-input" id="driver_id" name="driver_id" required><option value="">Selecciona…</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected((string) old('driver_id', $deliveryRun->driver_id) === (string) $driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="vehicle_id">Vehículo</label><select class="form-input" id="vehicle_id" name="vehicle_id"><option value="">Sin vehículo definido</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $deliveryRun->vehicle_id) === $vehicle->id)>{{ $vehicle->description }} · {{ $vehicle->license_plate ?: $vehicle->code }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label class="form-label" for="notes">Notas operativas</label><textarea class="form-input min-h-28" id="notes" name="notes" maxlength="2000" placeholder="Indicaciones de carga, zona o recorrido…">{{ old('notes', $deliveryRun->notes) }}</textarea></div>
            </div>
        </section>

        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm leading-6 text-sky-950">
            <p class="font-bold">Diseñada para varias rutas</p>
            <p>Los pedidos se ordenan por parada dentro de la jornada. El recorrido puede combinar rutas sin perder la ruta original de cada cliente.</p>
        </section>

        <div class="flex justify-end gap-3"><a class="btn-secondary" href="{{ $editing ? route('delivery-runs.show', $deliveryRun) : route('delivery-runs.index') }}">Cancelar</a><button class="btn-primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear jornada' }}</button></div>
    </form>
@endsection
