@extends('layouts.app')

@php($editing = $salesRoute->exists)
@section('title', $editing ? 'Editar ruta' : 'Nueva ruta')
@section('page-title', $editing ? 'Editar ruta' : 'Nueva ruta')
@section('page-subtitle', 'Define la ruta y sus responsables habituales; después podrás agregar las visitas.')

@section('content')
    <form method="POST" action="{{ $editing ? route('routes.update', $salesRoute) : route('routes.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card p-5 sm:p-7">
            <div class="mb-6"><h2 class="text-lg font-bold text-ink-950">Información de la ruta</h2><p class="text-sm text-ink-600">El código permite identificarla aunque el nombre cambie.</p></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="code">Código *</label>
                    <input class="form-input uppercase" id="code" name="code" required maxlength="40" value="{{ old('code', $salesRoute->code) }}" placeholder="RUTA-NORTE">
                </div>
                <div>
                    <label class="form-label" for="name">Nombre *</label>
                    <input class="form-input" id="name" name="name" required maxlength="255" value="{{ old('name', $salesRoute->name) }}" placeholder="Ruta Norte">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="description">Descripción</label>
                    <textarea class="form-input min-h-24" id="description" name="description" maxlength="2000" placeholder="Zona cubierta, frecuencia u observaciones generales">{{ old('description', $salesRoute->description) }}</textarea>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6"><h2 class="text-lg font-bold text-ink-950">Responsables habituales</h2><p class="text-sm text-ink-600">La asignación queda opcional para que puedas terminarla cuando el personal esté definido.</p></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="salesperson_id">Preventista</label>
                    <select class="form-input" id="salesperson_id" name="salesperson_id">
                        <option value="">Sin asignar</option>
                        @foreach ($salespeople as $salesperson)
                            <option value="{{ $salesperson->id }}" @selected((string) old('salesperson_id', $salesRoute->salesperson_id) === (string) $salesperson->id)>{{ $salesperson->name }}{{ $salesperson->is_active ? '' : ' · Inactivo' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="driver_id">Repartidor</label>
                    <select class="form-input" id="driver_id" name="driver_id">
                        <option value="">Sin asignar</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) old('driver_id', $salesRoute->driver_id) === (string) $driver->id)>{{ $driver->name }}{{ $driver->is_active ? '' : ' · Inactivo' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4">
                        <input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $salesRoute->exists ? $salesRoute->is_active : true))>
                        <span><span class="block text-sm font-semibold">Ruta activa</span><span class="text-xs text-ink-600">Disponible para planificar visitas y pedidos.</span></span>
                    </label>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3"><a class="btn-secondary" href="{{ $editing ? route('routes.show', $salesRoute) : route('routes.index') }}">Cancelar</a><button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear ruta' }}</button></div>
    </form>
@endsection
