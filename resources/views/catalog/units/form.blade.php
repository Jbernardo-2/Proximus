@extends('layouts.app')

@php($editing = $measurementUnit->exists)
@section('title', $editing ? 'Editar unidad' : 'Nueva unidad')
@section('page-title', $editing ? 'Editar unidad' : 'Nueva unidad')
@section('page-subtitle', 'Indica si admite fracciones para conservar la precisión correcta.')

@section('content')
    <div class="max-w-3xl"><form method="POST" action="{{ $editing ? route('measurement-units.update', $measurementUnit) : route('measurement-units.store') }}" class="card p-5 sm:p-7">@csrf @if($editing) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label" for="name">Nombre *</label><input class="form-input" id="name" name="name" required maxlength="80" value="{{ old('name', $measurementUnit->name) }}" placeholder="Unidad"></div><div><label class="form-label" for="symbol">Símbolo *</label><input class="form-input" id="symbol" name="symbol" required maxlength="20" value="{{ old('symbol', $measurementUnit->symbol) }}" placeholder="u"></div><div><label class="form-label" for="decimal_places">Cantidad de decimales *</label><select class="form-input" id="decimal_places" name="decimal_places">@for($i = 0; $i <= 6; $i++)<option value="{{ $i }}" @selected((int) old('decimal_places', $measurementUnit->decimal_places ?? 0) === $i)>{{ $i }}</option>@endfor</select><p class="form-help">Usa 0 para productos contados por pieza; 3 suele servir para peso o volumen.</p></div><div class="sm:col-span-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-3 rounded-xl border border-stone-200 p-4"><input class="size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $measurementUnit->exists ? $measurementUnit->is_active : true))><span><span class="block text-sm font-semibold">Unidad activa</span><span class="text-xs text-ink-600">Disponible para nuevos productos.</span></span></label></div></div>
        <div class="mt-7 flex justify-end gap-3"><a class="btn-secondary" href="{{ route('measurement-units.index') }}">Cancelar</a><button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear unidad' }}</button></div>
    </form></div>
@endsection
