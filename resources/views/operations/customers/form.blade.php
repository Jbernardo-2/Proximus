@extends('layouts.app')

@php($editing = $customer->exists)
@section('title', $editing ? 'Editar cliente' : 'Nuevo cliente')
@section('page-title', $editing ? 'Editar cliente' : 'Nuevo cliente')
@section('page-subtitle', 'La ficha del negocio se reutiliza en todas las rutas donde sea atendido.')

@section('content')
    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Identificación</h2>
                <p class="text-sm text-ink-600">Datos con los que el equipo encontrará al cliente.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="form-label" for="code">Código interno *</label>
                    <input class="form-input uppercase" id="code" name="code" required maxlength="40" value="{{ old('code', $customer->code) }}" placeholder="CLI-001">
                </div>
                <div class="sm:col-span-1 xl:col-span-2">
                    <label class="form-label" for="business_name">Nombre del negocio *</label>
                    <input class="form-input" id="business_name" name="business_name" required maxlength="255" value="{{ old('business_name', $customer->business_name) }}" placeholder="Ej. Pulpería La Esperanza">
                </div>
                <div>
                    <label class="form-label" for="business_type">Tipo de negocio</label>
                    <input class="form-input" id="business_type" name="business_type" maxlength="80" list="business-types" value="{{ old('business_type', $customer->business_type) }}" placeholder="Pulpería">
                    <datalist id="business-types"><option value="Pulpería"><option value="Minimarket"><option value="Abarrotería"><option value="Supermercado"><option value="Restaurante"></datalist>
                    <p class="form-help">Puedes escribir cualquier tipo; la lista solo ofrece sugerencias.</p>
                </div>
                <div>
                    <label class="form-label" for="contact_name">Persona de contacto</label>
                    <input class="form-input" id="contact_name" name="contact_name" maxlength="255" value="{{ old('contact_name', $customer->contact_name) }}">
                </div>
                <div>
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-input" id="email" name="email" type="email" maxlength="255" value="{{ old('email', $customer->email) }}">
                </div>
                <div>
                    <label class="form-label" for="phone">Teléfono</label>
                    <input class="form-input" id="phone" name="phone" maxlength="40" value="{{ old('phone', $customer->phone) }}">
                </div>
                <div>
                    <label class="form-label" for="whatsapp">WhatsApp</label>
                    <input class="form-input" id="whatsapp" name="whatsapp" maxlength="40" value="{{ old('whatsapp', $customer->whatsapp) }}">
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Ubicación</h2>
                <p class="text-sm text-ink-600">La dirección es obligatoria; las coordenadas quedan listas para la futura app de reparto.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label" for="address">Dirección *</label>
                    <textarea class="form-input min-h-24" id="address" name="address" required maxlength="2000" placeholder="Barrio, calle, número o indicaciones principales">{{ old('address', $customer->address) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="reference">Referencia para llegar</label>
                    <textarea class="form-input min-h-20" id="reference" name="reference" maxlength="2000" placeholder="Ej. Frente a la escuela, portón azul">{{ old('reference', $customer->reference) }}</textarea>
                </div>
                <div>
                    <label class="form-label" for="latitude">Latitud</label>
                    <input class="form-input" id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $customer->latitude) }}" placeholder="14.0723000">
                </div>
                <div>
                    <label class="form-label" for="longitude">Longitud</label>
                    <input class="form-input" id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $customer->longitude) }}" placeholder="-87.1921000">
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="grid gap-5 sm:grid-cols-[1fr_20rem]">
                <div>
                    <label class="form-label" for="notes">Notas internas</label>
                    <textarea class="form-input min-h-28" id="notes" name="notes" maxlength="4000" placeholder="Horarios, preferencias o datos útiles para el equipo">{{ old('notes', $customer->notes) }}</textarea>
                </div>
                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4">
                        <input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $customer->exists ? $customer->is_active : true))>
                        <span><span class="block text-sm font-semibold">Cliente activo</span><span class="text-xs text-ink-600">Disponible para agregarlo a nuevas visitas.</span></span>
                    </label>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear cliente' }}</button>
        </div>
    </form>
@endsection
