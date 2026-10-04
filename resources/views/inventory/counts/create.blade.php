@extends('layouts.app')

@section('title', 'Nuevo conteo')
@section('page-title', 'Nuevo conteo físico')
@section('page-subtitle', 'Se capturará una fotografía de la existencia actual de todos los productos activos.')
@section('header-actions')<a href="{{ route('inventory-counts.index') }}" class="btn-secondary">Volver</a>@endsection

@section('content')
    <form method="POST" action="{{ route('inventory-counts.store') }}" class="space-y-6">@csrf<section class="card p-5 sm:p-7"><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><strong>Importante:</strong> solo puede existir un conteo abierto por bodega. Si se registra una entrada o salida mientras cuentas, el sistema impedirá aplicarlo para evitar sobrescribir movimientos legítimos.</div><div class="mt-6 grid gap-5 sm:grid-cols-2"><div><label class="form-label" for="warehouse_id">Bodega *</label><select class="form-input" id="warehouse_id" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $warehouses->firstWhere('is_default', true)?->id) === $warehouse->id)>{{ $warehouse->name }} · {{ $warehouse->code }}</option>@endforeach</select></div><div><label class="form-label" for="counted_on">Fecha *</label><input class="form-input" id="counted_on" name="counted_on" type="date" max="{{ now()->toDateString() }}" required value="{{ old('counted_on', now()->toDateString()) }}"></div><div class="sm:col-span-2"><label class="form-label" for="notes">Indicaciones</label><textarea class="form-input min-h-24" id="notes" name="notes" maxlength="2000" placeholder="Zona a revisar, responsables o cualquier observación">{{ old('notes') }}</textarea></div></div></section><div class="flex justify-end gap-3"><a class="btn-secondary" href="{{ route('inventory-counts.index') }}">Cancelar</a><button class="btn-primary" @disabled($warehouses->isEmpty())>Iniciar conteo</button></div></form>
@endsection
