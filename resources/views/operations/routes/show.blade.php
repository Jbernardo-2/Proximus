@extends('layouts.app')

@section('title', $salesRoute->name)
@section('page-title', $salesRoute->name)
@section('page-subtitle', $salesRoute->code.' · '.$salesRoute->stops->count().' visitas programadas')
@section('header-actions')<a href="{{ route('routes.edit', $salesRoute) }}" class="btn-primary">Editar ruta</a>@endsection

@section('content')
    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Estado</p><div class="mt-3"><x-status-badge :active="$salesRoute->is_active" /></div></article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Preventista</p><p class="mt-2 font-bold text-ink-950">{{ $salesRoute->salesperson?->name ?: 'Sin asignar' }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Repartidor</p><p class="mt-2 font-bold text-ink-950">{{ $salesRoute->driver?->name ?: 'Sin asignar' }}</p></article>
            <article class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Visitas semanales</p><p class="mt-1 text-3xl font-black text-ink-950">{{ $salesRoute->stops->count() }}</p></article>
        </section>

        @if ($salesRoute->description)
            <section class="card p-5"><p class="text-xs font-semibold text-ink-600 uppercase">Descripción</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-800">{{ $salesRoute->description }}</p></section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="card overflow-hidden">
                <div class="border-b border-stone-100 px-5 py-4">
                    <h2 class="font-bold text-ink-950">Calendario de visitas</h2>
                    <p class="text-sm text-ink-600">Ordenado por día y posición. Repetir un cliente en otra ruta está permitido.</p>
                </div>
                @if ($salesRoute->stops->isEmpty())
                    <div class="p-6"><x-empty-state title="Ruta sin visitas" description="Agrega el primer cliente desde el formulario de esta página." /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[780px]">
                            <thead class="bg-stone-50"><tr><th class="table-heading">Día y orden</th><th class="table-heading">Cliente</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead>
                            <tbody>
                                @foreach ($salesRoute->stops as $stop)
                                    <tr class="align-top hover:bg-mint-50/30">
                                        <td class="table-cell"><p class="font-semibold text-ink-950">{{ $stop->visit_day->label() }}</p><p class="text-xs text-ink-600">Parada #{{ $stop->visit_order }}</p></td>
                                        <td class="table-cell"><a class="font-semibold text-ink-950 hover:text-leaf-700 hover:underline" href="{{ route('customers.show', $stop->customer) }}">{{ $stop->customer->business_name }}</a><p class="mt-0.5 max-w-sm truncate text-xs text-ink-600" title="{{ $stop->customer->address }}">{{ $stop->customer->code }} · {{ $stop->customer->address }}</p>@if($stop->notes)<p class="mt-1 text-xs text-ink-600">{{ $stop->notes }}</p>@endif</td>
                                        <td class="table-cell"><x-status-badge :active="$stop->is_active" /></td>
                                        <td class="table-cell">
                                            <div class="flex justify-end gap-2">
                                                <details>
                                                    <summary class="btn-secondary min-h-9 cursor-pointer list-none px-3 py-1.5">Configurar</summary>
                                                    <div class="mt-2 w-[22rem] rounded-2xl border border-stone-200 bg-white p-4 text-left shadow-lg">
                                                        <form method="POST" action="{{ route('routes.stops.update', [$salesRoute, $stop]) }}" class="space-y-3">
                                                            @csrf @method('PUT')
                                                            <div><label class="form-label" for="customer-{{ $stop->id }}">Cliente</label><select class="form-input" id="customer-{{ $stop->id }}" name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected($customer->is($stop->customer))>{{ $customer->business_name }} · {{ $customer->code }}</option>@endforeach</select></div>
                                                            <div class="grid grid-cols-2 gap-3"><div><label class="form-label" for="day-{{ $stop->id }}">Día</label><select class="form-input" id="day-{{ $stop->id }}" name="visit_day" required>@foreach($weekdays as $value => $label)<option value="{{ $value }}" @selected($stop->visit_day->value === $value)>{{ $label }}</option>@endforeach</select></div><div><label class="form-label" for="order-{{ $stop->id }}">Orden</label><input class="form-input" id="order-{{ $stop->id }}" name="visit_order" type="number" min="1" max="65535" required value="{{ $stop->visit_order }}"></div></div>
                                                            <div><label class="form-label" for="notes-{{ $stop->id }}">Notas</label><textarea class="form-input min-h-20" id="notes-{{ $stop->id }}" name="notes" maxlength="2000">{{ $stop->notes }}</textarea></div>
                                                            <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($stop->is_active)> Visita activa</label>
                                                            <button class="btn-primary w-full" type="submit">Guardar visita</button>
                                                        </form>
                                                    </div>
                                                </details>
                                                <form method="POST" action="{{ route('routes.stops.destroy', [$salesRoute, $stop]) }}" data-confirm="¿Retirar esta visita de la ruta?">@csrf @method('DELETE')<button class="btn-danger" type="submit">Retirar</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <aside class="space-y-5">
                <section class="card p-5">
                    <p class="text-sm font-semibold text-leaf-700">Agregar visita</p>
                    <h2 class="mt-1 text-lg font-black text-ink-950">Programa un cliente</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-600">El mismo cliente puede programarse en otra ruta o en otro día de esta ruta.</p>
                    @if ($customers->isEmpty())
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Necesitas un cliente activo. <a class="font-semibold underline" href="{{ route('customers.create') }}">Crear cliente</a></div>
                    @else
                        <form method="POST" action="{{ route('routes.stops.store', $salesRoute) }}" class="mt-5 space-y-4">
                            @csrf
                            <div><label class="form-label" for="customer_id">Cliente *</label><select class="form-input" id="customer_id" name="customer_id" required><option value="">Selecciona…</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') === $customer->id)>{{ $customer->business_name }} · {{ $customer->code }}</option>@endforeach</select></div>
                            <div class="grid grid-cols-2 gap-3"><div><label class="form-label" for="visit_day">Día *</label><select class="form-input" id="visit_day" name="visit_day" required><option value="">Selecciona…</option>@foreach($weekdays as $value => $label)<option value="{{ $value }}" @selected((string) old('visit_day') === (string) $value)>{{ $label }}</option>@endforeach</select></div><div><label class="form-label" for="visit_order">Orden *</label><input class="form-input" id="visit_order" name="visit_order" type="number" min="1" max="65535" required value="{{ old('visit_order', $nextVisitOrder) }}"></div></div>
                            <div><label class="form-label" for="notes">Notas</label><textarea class="form-input min-h-20" id="notes" name="notes" maxlength="2000" placeholder="Indicaciones para esta visita">{{ old('notes') }}</textarea></div>
                            <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', true))> Visita activa</label>
                            <button class="btn-primary w-full" type="submit">Agregar a la ruta</button>
                        </form>
                    @endif
                </section>

                <section class="card p-5">
                    <h2 class="font-bold text-ink-950">Eliminar ruta</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-600">Solo se puede eliminar cuando no tiene visitas. Puedes desactivarla para conservar su configuración.</p>
                    <form class="mt-4" method="POST" action="{{ route('routes.destroy', $salesRoute) }}" data-confirm="¿Eliminar esta ruta? Esta acción no se puede deshacer.">@csrf @method('DELETE')<button class="btn-danger w-full" type="submit">Eliminar ruta</button></form>
                </section>
            </aside>
        </div>
    </div>
@endsection
