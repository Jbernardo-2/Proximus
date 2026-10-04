@extends('layouts.app')

@php($editing = $vehicle->exists)
@section('title', $editing ? 'Editar vehículo' : 'Nuevo vehículo')
@section('page-title', $editing ? 'Editar vehículo' : 'Nuevo vehículo')
@section('page-subtitle', 'Identificación operativa para planificar el reparto.')
@section('header-actions')<a href="{{ route('vehicles.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" class="mx-auto max-w-3xl space-y-6">
        @csrf @if($editing) @method('PUT') @endif
        <section class="card p-5 sm:p-7">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label class="form-label" for="code">Código *</label><input class="form-input uppercase" id="code" name="code" required maxlength="40" value="{{ old('code', $vehicle->code) }}" placeholder="VEH-001"></div>
                <div><label class="form-label" for="license_plate">Placa</label><input class="form-input uppercase" id="license_plate" name="license_plate" maxlength="30" value="{{ old('license_plate', $vehicle->license_plate) }}" placeholder="HAA-0000"></div>
                <div class="sm:col-span-2"><label class="form-label" for="description">Descripción *</label><input class="form-input" id="description" name="description" required maxlength="180" value="{{ old('description', $vehicle->description) }}" placeholder="Camión blanco de reparto"></div>
                <label class="flex items-center gap-3 rounded-xl border border-stone-200 p-4 sm:col-span-2"><input class="size-5 rounded border-stone-300 text-leaf-600" type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle->exists ? $vehicle->is_active : true))><span><span class="block font-semibold text-ink-950">Vehículo activo</span><span class="text-sm text-ink-600">Disponible para nuevas jornadas.</span></span></label>
            </div>
        </section>
        <div class="flex justify-end gap-3"><a class="btn-secondary" href="{{ route('vehicles.index') }}">Cancelar</a><button class="btn-primary" type="submit">Guardar vehículo</button></div>
    </form>
@endsection
