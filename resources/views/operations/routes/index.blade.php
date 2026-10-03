@extends('layouts.app')

@section('title', 'Rutas')
@section('page-title', 'Rutas')
@section('page-subtitle', 'Configura responsables y el calendario semanal de visitas.')
@section('header-actions')<a href="{{ route('routes.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nueva ruta</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4">
            <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_11rem_auto]">
                <div>
                    <label class="sr-only" for="search">Buscar ruta</label>
                    <input class="form-input" id="search" type="search" name="search" value="{{ $search }}" placeholder="Nombre, código o responsable…">
                </div>
                <div>
                    <label class="sr-only" for="status">Estado</label>
                    <select class="form-input" id="status" name="status">
                        <option value="">Todos los estados</option>
                        <option value="active" @selected($selectedStatus === 'active')>Activas</option>
                        <option value="inactive" @selected($selectedStatus === 'inactive')>Inactivas</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="btn-secondary flex-1" type="submit">Filtrar</button>
                    @if ($search !== '' || in_array($selectedStatus, ['active', 'inactive'], true))
                        <a class="btn-secondary px-3" href="{{ route('routes.index') }}" aria-label="Limpiar filtros">×</a>
                    @endif
                </div>
            </form>
            <p class="mt-3 text-sm text-ink-600">{{ $salesRoutes->total() }} {{ $salesRoutes->total() === 1 ? 'ruta registrada' : 'rutas registradas' }}</p>
        </div>

        @if ($salesRoutes->isEmpty())
            <div class="p-6">
                <x-empty-state title="No hay rutas" description="Crea una ruta y luego organiza en ella las visitas de tus clientes.">
                    <x-slot:action><a href="{{ route('routes.create') }}" class="btn-primary">Crear ruta</a></x-slot:action>
                </x-empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-stone-50"><tr><th class="table-heading">Ruta</th><th class="table-heading">Preventista</th><th class="table-heading">Repartidor</th><th class="table-heading">Visitas</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($salesRoutes as $salesRoute)
                            <tr class="hover:bg-mint-50/40">
                                <td class="table-cell"><a class="font-semibold text-ink-950 hover:text-leaf-700 hover:underline" href="{{ route('routes.show', $salesRoute) }}">{{ $salesRoute->name }}</a><p class="mt-0.5 text-xs text-ink-600">{{ $salesRoute->code }}</p></td>
                                <td class="table-cell">{{ $salesRoute->salesperson?->name ?: 'Sin asignar' }}</td>
                                <td class="table-cell">{{ $salesRoute->driver?->name ?: 'Sin asignar' }}</td>
                                <td class="table-cell"><span class="inline-flex rounded-full bg-mint-100 px-2.5 py-1 text-xs font-semibold text-leaf-700">{{ $salesRoute->stops_count }}</span></td>
                                <td class="table-cell"><x-status-badge :active="$salesRoute->is_active" /></td>
                                <td class="table-cell"><div class="flex justify-end gap-2"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('routes.show', $salesRoute) }}">Ver</a><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('routes.edit', $salesRoute) }}">Editar</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-4">{{ $salesRoutes->links() }}</div>
        @endif
    </div>
@endsection
