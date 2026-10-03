@extends('layouts.app')

@php($editing = $brand->exists)
@section('title', $editing ? 'Editar marca' : 'Nueva marca')
@section('page-title', $editing ? 'Editar marca' : 'Nueva marca')
@section('page-subtitle', 'La marca será opcional al registrar productos.')

@section('content')
    <div class="max-w-3xl"><form method="POST" action="{{ $editing ? route('brands.update', $brand) : route('brands.store') }}" class="card p-5 sm:p-7">@csrf @if($editing) @method('PUT') @endif
        <div class="space-y-5"><div><label class="form-label" for="name">Nombre *</label><input class="form-input" id="name" name="name" required maxlength="120" value="{{ old('name', $brand->name) }}"></div><div><label class="form-label" for="description">Descripción</label><textarea class="form-input min-h-28" id="description" name="description" maxlength="2000">{{ old('description', $brand->description) }}</textarea></div><div><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-3 rounded-xl border border-stone-200 p-4"><input class="size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $brand->exists ? $brand->is_active : true))><span><span class="block text-sm font-semibold">Marca activa</span><span class="text-xs text-ink-600">Disponible para nuevos productos.</span></span></label></div></div>
        <div class="mt-7 flex justify-end gap-3"><a class="btn-secondary" href="{{ route('brands.index') }}">Cancelar</a><button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear marca' }}</button></div>
    </form></div>
@endsection
