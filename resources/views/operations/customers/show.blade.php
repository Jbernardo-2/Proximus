@extends('layouts.app')

@section('title', $customer->business_name)
@section('page-title', $customer->business_name)
@section('page-subtitle', $customer->code.' · '.($customer->business_type ?: 'Tipo de negocio sin definir'))
@section('header-actions')
    <a href="{{ route('customers.edit', $customer) }}" class="btn-primary">Editar cliente</a>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="card p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Ficha del cliente</p>
                        <h2 class="mt-2 text-xl font-black text-ink-950">{{ $customer->business_name }}</h2>
                    </div>
                    <x-status-badge :active="$customer->is_active" />
                </div>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Contacto</dt><dd class="mt-1 font-semibold text-ink-950">{{ $customer->contact_name ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Teléfono</dt><dd class="mt-1 text-ink-800">{{ $customer->phone ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">WhatsApp</dt><dd class="mt-1 text-ink-800">{{ $customer->whatsapp ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Correo</dt><dd class="mt-1 break-all text-ink-800">{{ $customer->email ?: 'Sin registrar' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Dirección</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $customer->address }}</dd></div>
                    @if ($customer->reference)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Referencia</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $customer->reference }}</dd></div>
                    @endif
                    @if ($customer->latitude !== null && $customer->longitude !== null)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Coordenadas</dt><dd class="mt-1 font-mono text-sm text-ink-800">{{ $customer->latitude }}, {{ $customer->longitude }}</dd></div>
                    @endif
                </dl>
                @if ($customer->notes)
                    <div class="mt-6 rounded-xl bg-sand-50 p-4"><p class="text-xs font-semibold text-ink-600 uppercase">Notas internas</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-800">{{ $customer->notes }}</p></div>
                @endif
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-stone-100 px-5 py-4">
                    <h2 class="font-bold text-ink-950">Visitas programadas</h2>
                    <p class="text-sm text-ink-600">Un mismo cliente puede aparecer en varias rutas y días.</p>
                </div>
                @if ($customer->routeStops->isEmpty())
                    <div class="p-6"><x-empty-state title="Sin rutas asignadas" description="Abre una ruta para programar la visita de este cliente." /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px]">
                            <thead class="bg-stone-50"><tr><th class="table-heading">Ruta</th><th class="table-heading">Día</th><th class="table-heading">Orden</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acción</th></tr></thead>
                            <tbody>
                                @foreach ($customer->routeStops as $stop)
                                    <tr>
                                        <td class="table-cell"><p class="font-semibold text-ink-950">{{ $stop->salesRoute->name }}</p><p class="text-xs text-ink-600">{{ $stop->salesRoute->code }}</p></td>
                                        <td class="table-cell">{{ $stop->visit_day->label() }}</td>
                                        <td class="table-cell">#{{ $stop->visit_order }}</td>
                                        <td class="table-cell"><x-status-badge :active="$stop->is_active" /></td>
                                        <td class="table-cell text-right"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('routes.show', $stop->salesRoute) }}">Ver ruta</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <div class="card p-5">
                <p class="text-sm font-semibold text-leaf-700">Resumen</p>
                <p class="mt-2 text-3xl font-black text-ink-950">{{ $customer->routeStops->count() }}</p>
                <p class="text-sm text-ink-600">visitas semanales configuradas</p>
            </div>
            <div class="card p-5">
                <h2 class="font-bold text-ink-950">Eliminar cliente</h2>
                <p class="mt-2 text-sm leading-6 text-ink-600">Solo se puede eliminar cuando ya no tiene visitas asignadas. Para conservar el historial, también puedes desactivarlo.</p>
                <form class="mt-4" method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="¿Eliminar este cliente? Esta acción no se puede deshacer.">
                    @csrf @method('DELETE')
                    <button class="btn-danger w-full" type="submit">Eliminar cliente</button>
                </form>
            </div>
        </aside>
    </div>
@endsection
