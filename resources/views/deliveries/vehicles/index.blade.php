@extends('layouts.app')

@section('title', 'Vehículos')
@section('page-title', 'Vehículos')
@section('page-subtitle', 'Unidades disponibles para las jornadas de reparto.')
@section('header-actions')<a href="{{ route('vehicles.create') }}" class="btn-primary">＋ Nuevo vehículo</a>@endsection

@section('content')
    <div class="space-y-6">
        <nav class="flex flex-wrap gap-2 text-sm"><a class="btn-secondary" href="{{ route('delivery-runs.index') }}">Jornadas</a><a class="btn-secondary border-leaf-600 text-leaf-700" href="{{ route('vehicles.index') }}">Vehículos</a></nav>

        <section class="card p-5"><form method="GET" action="{{ route('vehicles.index') }}" class="flex flex-col gap-3 sm:flex-row"><div class="flex-1"><label class="form-label" for="search">Buscar vehículo</label><input class="form-input" id="search" name="search" value="{{ $search }}" placeholder="Código, placa o descripción"></div><div class="flex items-end gap-2"><button class="btn-primary">Buscar</button><a class="btn-secondary" href="{{ route('vehicles.index') }}">Limpiar</a></div></form></section>

        <section class="card overflow-hidden">
            <div class="border-b border-stone-100 px-5 py-4"><h2 class="font-bold text-ink-950">Flota registrada</h2><p class="text-sm text-ink-600">Los vehículos usados conservan su historial aunque después sean desactivados.</p></div>
            @if($vehicles->isEmpty())
                <div class="p-6"><x-empty-state title="Sin vehículos" description="Registra el primer vehículo o deja la jornada sin unidad definida." /></div>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[760px]"><thead class="bg-stone-50"><tr><th class="table-heading">Código</th><th class="table-heading">Descripción</th><th class="table-heading">Placa</th><th class="table-heading text-right">Jornadas</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acción</th></tr></thead><tbody>
                    @foreach($vehicles as $vehicle)<tr><td class="table-cell font-bold text-ink-950">{{ $vehicle->code }}</td><td class="table-cell">{{ $vehicle->description }}</td><td class="table-cell font-semibold">{{ $vehicle->license_plate ?: 'Sin placa' }}</td><td class="table-cell text-right font-bold">{{ $vehicle->delivery_runs_count }}</td><td class="table-cell"><span class="rounded-full {{ $vehicle->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-700' }} px-2.5 py-1 text-xs font-bold">{{ $vehicle->is_active ? 'Activo' : 'Inactivo' }}</span></td><td class="table-cell text-right"><a class="btn-secondary" href="{{ route('vehicles.edit', $vehicle) }}">Editar</a></td></tr>@endforeach
                </tbody></table></div>
                <div class="border-t border-stone-100 px-5 py-4">{{ $vehicles->links() }}</div>
            @endif
        </section>
    </div>
@endsection
