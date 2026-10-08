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

        <section class="card p-5 sm:p-7" data-customer-location>
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Ubicación</h2>
                <p class="text-sm text-ink-600">Escribe la dirección y ubica el negocio con tu GPS o tocando el mapa. Las coordenadas se completan automáticamente.</p>
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
                <div class="sm:col-span-2">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="form-label mb-0">Punto de entrega</p>
                            <p class="text-xs text-ink-600">Puedes mover el mapa, acercarlo y tocar el lugar exacto.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button class="btn-primary" type="button" data-location-current>
                                <svg class="size-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                                Usar mi ubicación
                            </button>
                            <button class="btn-secondary" type="button" data-location-clear>Quitar punto</button>
                        </div>
                    </div>
                    <div class="relative h-80 overflow-hidden rounded-2xl border border-stone-300 bg-stone-100 select-none" data-location-map role="application" aria-label="Mapa para seleccionar el punto de entrega" tabindex="0">
                        <div class="absolute inset-0 cursor-crosshair overflow-hidden" data-location-tiles></div>
                        <div class="pointer-events-none absolute hidden -translate-x-1/2 -translate-y-full drop-shadow-lg" data-location-marker aria-hidden="true">
                            <svg class="h-10 w-10 text-red-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>
                        </div>
                        <div class="absolute top-3 right-3 flex flex-col overflow-hidden rounded-xl border border-stone-300 bg-white shadow-md">
                            <button class="flex size-10 items-center justify-center text-xl font-bold hover:bg-stone-50" type="button" data-location-zoom-in aria-label="Acercar mapa">+</button>
                            <button class="flex size-10 items-center justify-center border-t border-stone-200 text-xl font-bold hover:bg-stone-50" type="button" data-location-zoom-out aria-label="Alejar mapa">−</button>
                        </div>
                        <p class="absolute right-2 bottom-1 rounded bg-white/90 px-1.5 py-0.5 text-[10px] text-ink-600">© <a class="underline" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a></p>
                    </div>
                    <p class="mt-2 min-h-5 text-sm text-ink-600" data-location-status aria-live="polite">{{ filled(old('latitude', $customer->latitude)) && filled(old('longitude', $customer->longitude)) ? 'Punto guardado. Puedes ajustarlo en el mapa.' : 'Aún no has marcado un punto; las coordenadas son opcionales.' }}</p>
                </div>
                <details class="sm:col-span-2 rounded-xl border border-stone-200 bg-stone-50 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-ink-800">Ver o escribir coordenadas manualmente</summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label" for="latitude">Latitud</label>
                            <input class="form-input" id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $customer->latitude) }}" placeholder="14.0723000" data-location-latitude>
                        </div>
                        <div>
                            <label class="form-label" for="longitude">Longitud</label>
                            <input class="form-input" id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $customer->longitude) }}" placeholder="-87.1921000" data-location-longitude>
                        </div>
                    </div>
                </details>
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
