@extends('layouts.app')

@section('title', 'Teléfonos')
@section('page-title', 'Teléfonos')
@section('page-subtitle', 'Teléfonos con la app móvil. Bloquea uno si se pierde o cambia de manos.')

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4">
            <form method="GET" class="flex gap-2">
                <label class="sr-only" for="search">Buscar teléfono</label>
                <input class="form-input max-w-md" id="search" type="search" name="search" value="{{ $search }}" placeholder="Código, teléfono o usuario…">
                <button class="btn-secondary" type="submit">Buscar</button>
                @if ($search !== '')
                    <a class="btn-secondary px-3" href="{{ route('devices.index') }}" aria-label="Limpiar búsqueda">×</a>
                @endif
            </form>
        </div>

        @if ($devices->isEmpty())
            <div class="p-6">
                <x-empty-state title="No hay teléfonos registrados" description="Cada teléfono aparece aquí la primera vez que alguien inicia sesión en la app móvil." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-heading">Teléfono</th>
                            <th class="table-heading">Último usuario</th>
                            <th class="table-heading">Última conexión</th>
                            <th class="table-heading">Última sincronización</th>
                            <th class="table-heading">Estado</th>
                            <th class="table-heading text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr class="hover:bg-mint-50/40">
                                <td class="table-cell">
                                    <p class="font-semibold text-ink-950"><span class="font-mono">{{ $device->code }}</span> · {{ $device->name }}</p>
                                    <p class="text-xs text-ink-600">{{ collect([$device->platform, $device->app_version ? 'v'.$device->app_version : null])->filter()->implode(' · ') ?: '—' }}</p>
                                </td>
                                <td class="table-cell">{{ $device->user?->name ?? '—' }}</td>
                                <td class="table-cell">
                                    @if ($device->last_seen_at)
                                        <span title="{{ $device->last_seen_at->format('d/m/Y H:i') }}">{{ $device->last_seen_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-ink-600">—</span>
                                    @endif
                                </td>
                                <td class="table-cell">
                                    @if ($device->last_sync_at)
                                        {{ \Illuminate\Support\Carbon::parse($device->last_sync_at)->diffForHumans() }}
                                    @else
                                        <span class="text-ink-600">Sin envíos</span>
                                    @endif
                                    @if ($device->rejected_operations_count > 0)
                                        <a class="mt-0.5 block text-xs font-semibold text-red-700 hover:underline" href="{{ route('reports.show', ['report' => 'sincronizacion', 'search' => $device->code]) }}">{{ $device->rejected_operations_count }} {{ $device->rejected_operations_count === 1 ? 'rechazada' : 'rechazadas' }}</a>
                                    @endif
                                </td>
                                <td class="table-cell">
                                    @if ($device->isRevoked())
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700" title="Por {{ $device->revokedBy?->name ?? '—' }}">Bloqueado {{ $device->revoked_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-mint-50 px-2.5 py-1 text-xs font-semibold text-leaf-700">Activo</span>
                                    @endif
                                </td>
                                <td class="table-cell text-right">
                                    @if ($device->isRevoked())
                                        <form method="POST" action="{{ route('devices.restore', $device) }}" data-confirm="¿Reactivar el teléfono {{ $device->code }}? Podrá volver a iniciar sesión.">
                                            @csrf
                                            <button class="btn-secondary min-h-9 px-3 py-1.5">Reactivar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('devices.revoke', $device) }}" data-confirm="¿Bloquear el teléfono {{ $device->code }}? Su sesión se cierra de inmediato y lo que tenga sin sincronizar ya no podrá enviarse.">
                                            @csrf
                                            <button class="btn-danger">Bloquear</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-4">{{ $devices->links() }}</div>
        @endif
    </div>
@endsection
