@extends('layouts.app')

@section('title', 'Usuarios')
@section('page-title', 'Usuarios')
@section('page-subtitle', 'Administra el acceso del personal a Proximus.')
@section('header-actions')<a href="{{ route('users.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo usuario</span></a>@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4">
            <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_12rem_11rem_auto]">
                <div>
                    <label class="sr-only" for="search">Buscar usuario</label>
                    <input class="form-input" id="search" type="search" name="search" value="{{ $search }}" placeholder="Buscar por nombre o correo…">
                </div>
                <div>
                    <label class="sr-only" for="role">Filtrar por rol</label>
                    <select class="form-input" id="role" name="role">
                        <option value="">Todos los roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="status">Filtrar por estado</label>
                    <select class="form-input" id="status" name="status">
                        <option value="">Todos los estados</option>
                        <option value="active" @selected($selectedStatus === 'active')>Activos</option>
                        <option value="inactive" @selected($selectedStatus === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="btn-secondary flex-1" type="submit">Filtrar</button>
                    @if ($search !== '' || $selectedRole !== null || in_array($selectedStatus, ['active', 'inactive'], true))
                        <a class="btn-secondary px-3" href="{{ route('users.index') }}" aria-label="Limpiar filtros">×</a>
                    @endif
                </div>
            </form>
            <p class="mt-3 text-sm text-ink-600">{{ $users->total() }} {{ $users->total() === 1 ? 'usuario registrado' : 'usuarios registrados' }}</p>
        </div>

        @if ($users->isEmpty())
            <div class="p-6">
                <x-empty-state title="No hay usuarios" description="Crea las cuentas del personal que utilizará Proximus.">
                    <x-slot:action><a href="{{ route('users.create') }}" class="btn-primary">Crear usuario</a></x-slot:action>
                </x-empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-heading">Usuario</th>
                            <th class="table-heading">Rol</th>
                            <th class="table-heading">Último acceso</th>
                            <th class="table-heading">Estado</th>
                            <th class="table-heading text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="hover:bg-mint-50/40">
                                <td class="table-cell">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-mint-100 font-bold text-leaf-700">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="truncate font-semibold text-ink-950">{{ $user->name }}</p>
                                                @if ($user->is(auth()->user()))
                                                    <span class="rounded-full bg-sun-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Tu cuenta</span>
                                                @endif
                                            </div>
                                            <p class="truncate text-xs text-ink-600">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="table-cell"><span class="inline-flex rounded-full bg-mint-50 px-2.5 py-1 text-xs font-semibold text-leaf-700">{{ $user->role->label() }}</span></td>
                                <td class="table-cell">
                                    @if ($user->last_login_at)
                                        <span title="{{ $user->last_login_at->format('d/m/Y H:i') }}">{{ $user->last_login_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-ink-600">Nunca</span>
                                    @endif
                                </td>
                                <td class="table-cell"><x-status-badge :active="$user->is_active" /></td>
                                <td class="table-cell text-right"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('users.edit', $user) }}">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-4">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
