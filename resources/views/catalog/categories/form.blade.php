@extends('layouts.app')

@php($editing = $category->exists)
@section('title', $editing ? 'Editar categoría' : 'Nueva categoría')
@section('page-title', $editing ? 'Editar categoría' : 'Nueva categoría')
@section('page-subtitle', 'Define una familia clara para clasificar los productos.')

@section('content')
    <div class="max-w-3xl">
        <form method="POST" action="{{ $editing ? route('categories.update', $category) : route('categories.store') }}" class="card p-5 sm:p-7">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="form-label" for="name">Nombre *</label><input class="form-input" id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="120"></div>
                <div class="sm:col-span-2"><label class="form-label" for="parent_id">Categoría superior</label><select class="form-input" id="parent_id" name="parent_id"><option value="">Sin categoría superior</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>@endforeach</select><p class="form-help">Opcional. Úsala para crear subcategorías.</p></div>
                <div class="sm:col-span-2"><label class="form-label" for="description">Descripción</label><textarea class="form-input min-h-28" id="description" name="description" maxlength="2000">{{ old('description', $category->description) }}</textarea></div>
                <div class="sm:col-span-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-3 rounded-xl border border-stone-200 p-4"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $category->exists ? $category->is_active : true)) class="size-4 rounded border-stone-300 text-leaf-700"><span><span class="block text-sm font-semibold">Categoría activa</span><span class="text-xs text-ink-600">Disponible al registrar y filtrar productos.</span></span></label></div>
            </div>
            <div class="mt-7 flex flex-wrap justify-end gap-3"><a class="btn-secondary" href="{{ route('categories.index') }}">Cancelar</a><button class="btn-primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear categoría' }}</button></div>
        </form>
    </div>
@endsection
