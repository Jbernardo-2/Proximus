@extends('layouts.app')

@php($editing = $warehouse->exists)
@section('title', $editing ? 'Editar bodega' : 'Nueva bodega')
@section('page-title', $editing ? 'Editar bodega' : 'Nueva bodega')
@section('page-subtitle', 'Configura el lugar desde donde se controla y prepara la mercancía.')
@section('header-actions')<a href="{{ route('warehouses.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" class="card p-5 sm:p-7">@csrf @if($editing)@method('PUT')@endif
        <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label" for="code">Código *</label><input class="form-input uppercase" id="code" name="code" maxlength="40" required value="{{ old('code', $warehouse->code) }}" placeholder="BOD-001"></div><div><label class="form-label" for="name">Nombre *</label><input class="form-input" id="name" name="name" maxlength="160" required value="{{ old('name', $warehouse->name) }}" placeholder="Bodega principal"></div><div class="sm:col-span-2"><label class="form-label" for="address">Dirección</label><textarea class="form-input min-h-24" id="address" name="address" maxlength="1000">{{ old('address', $warehouse->address) }}</textarea></div><div class="sm:col-span-2 grid gap-3 sm:grid-cols-2"><input type="hidden" name="is_default" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5" type="checkbox" name="is_default" value="1" @checked((bool)old('is_default', $warehouse->is_default))><span><span class="block text-sm font-semibold">Bodega predeterminada</span><span class="text-xs text-ink-600">Se selecciona automáticamente en nuevos pedidos.</span></span></label><input type="hidden" name="is_active" value="0"><label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4"><input class="mt-0.5" type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $warehouse->exists ? $warehouse->is_active : true))><span><span class="block text-sm font-semibold">Bodega activa</span><span class="text-xs text-ink-600">Disponible para movimientos y pedidos nuevos.</span></span></label></div></div>
        <div class="mt-6 flex justify-end gap-3"><a class="btn-secondary" href="{{ route('warehouses.index') }}">Cancelar</a><button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear bodega' }}</button></div>
    </form>
@endsection
