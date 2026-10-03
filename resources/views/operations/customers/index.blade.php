@extends('layouts.app')

@section('title', 'Clientes')
@section('page-title', 'Clientes')
@section('page-subtitle', 'Organiza los negocios atendidos y consulta sus visitas de ruta.')
@section('header-actions')<a href="{{ route('customers.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo cliente</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4">
            <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_13rem_11rem_auto]">
                <div>
                    <label class="sr-only" for="search">Buscar cliente</label>
                    <input class="form-input" id="search" type="search" name="search" value="{{ $search }}" placeholder="Nombre, código, contacto o teléfono…">
                </div>
                <div>
                    <label class="sr-only" for="business_type">Tipo de negocio</label>
                    <select class="form-input" id="business_type" name="business_type">
                        <option value="">Todos los tipos</option>
                        @foreach ($businessTypes as $businessType)
                            <option value="{{ $businessType }}" @selected($selectedBusinessType === $businessType)>{{ $businessType }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="status">Estado</label>
                    <select class="form-input" id="status" name="status">
                        <option value="">Todos los estados</option>
                        <option value="active" @selected($selectedStatus === 'active')>Activos</option>
                        <option value="inactive" @selected($selectedStatus === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="btn-secondary flex-1" type="submit">Filtrar</button>
                    @if ($search !== '' || $selectedBusinessType !== '' || in_array($selectedStatus, ['active', 'inactive'], true))
                        <a class="btn-secondary px-3" href="{{ route('customers.index') }}" aria-label="Limpiar filtros">×</a>
                    @endif
                </div>
            </form>
            <p class="mt-3 text-sm text-ink-600">{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente registrado' : 'clientes registrados' }}</p>
        </div>

        @if ($customers->isEmpty())
            <div class="p-6">
                <x-empty-state title="No hay clientes" description="Registra el primer negocio para poder incorporarlo a una o varias rutas.">
                    <x-slot:action><a href="{{ route('customers.create') }}" class="btn-primary">Crear cliente</a></x-slot:action>
                </x-empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-heading">Negocio</th>
                            <th class="table-heading">Contacto</th>
                            <th class="table-heading">Ubicación</th>
                            <th class="table-heading">Visitas</th>
                            <th class="table-heading">Estado</th>
                            <th class="table-heading text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr class="hover:bg-mint-50/40">
                                <td class="table-cell">
                                    <a class="font-semibold text-ink-950 hover:text-leaf-700 hover:underline" href="{{ route('customers.show', $customer) }}">{{ $customer->business_name }}</a>
                                    <p class="mt-0.5 text-xs text-ink-600">{{ $customer->code }}{{ $customer->business_type ? ' · '.$customer->business_type : '' }}</p>
                                </td>
                                <td class="table-cell">
                                    <p>{{ $customer->contact_name ?: 'Sin contacto' }}</p>
                                    <p class="mt-0.5 text-xs text-ink-600">{{ $customer->whatsapp ?: $customer->phone ?: 'Sin teléfono' }}</p>
                                </td>
                                <td class="table-cell"><p class="max-w-xs truncate" title="{{ $customer->address }}">{{ $customer->address }}</p></td>
                                <td class="table-cell"><span class="inline-flex rounded-full bg-mint-100 px-2.5 py-1 text-xs font-semibold text-leaf-700">{{ $customer->route_stops_count }}</span></td>
                                <td class="table-cell"><x-status-badge :active="$customer->is_active" /></td>
                                <td class="table-cell">
                                    <div class="flex justify-end gap-2">
                                        <a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('customers.show', $customer) }}">Ver</a>
                                        <a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('customers.edit', $customer) }}">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-4">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
