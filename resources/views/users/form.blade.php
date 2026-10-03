@extends('layouts.app')

@php
    $editing = $managedUser->exists;
    $editingOwnAccount = $editing && $managedUser->is(auth()->user());
    $selectedRole = old('role', $managedUser->role?->value ?? 'bodeguero');
@endphp

@section('title', $editing ? 'Editar usuario' : 'Nuevo usuario')
@section('page-title', $editing ? 'Editar usuario' : 'Nuevo usuario')
@section('page-subtitle', $editing ? 'Actualiza su acceso, rol o contraseña.' : 'Crea una cuenta para el personal de la operación.')

@section('content')
    <form method="POST" action="{{ $editing ? route('users.update', $managedUser) : route('users.store') }}" class="max-w-4xl space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        @if ($editingOwnAccount)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Estás editando tu propia cuenta. Para evitar perder el acceso, no puedes desactivarla ni retirar su rol de administrador.
            </div>
        @endif

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Información de acceso</h2>
                <p class="mt-1 text-sm text-ink-600">Datos que identifican al usuario al iniciar sesión.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="name">Nombre completo *</label>
                    <input class="form-input" id="name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name', $managedUser->name) }}">
                </div>
                <div>
                    <label class="form-label" for="email">Correo electrónico *</label>
                    <input class="form-input" id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email', $managedUser->email) }}">
                </div>
                <div>
                    <label class="form-label" for="role">Rol *</label>
                    @if ($editingOwnAccount)
                        <input type="hidden" name="role" value="{{ $managedUser->role->value }}">
                    @endif
                    <select class="form-input" id="role" name="role" required @disabled($editingOwnAccount)>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <p class="form-help">El administrador gestiona usuarios; supervisor y bodeguero administran el catálogo.</p>
                </div>
                <div>
                    <span class="form-label">Estado</span>
                    @if ($editingOwnAccount)
                        <input type="hidden" name="is_active" value="1">
                    @else
                        <input type="hidden" name="is_active" value="0">
                    @endif
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-stone-200 px-4 py-2.5">
                        <input class="size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editing ? $managedUser->is_active : true)) @disabled($editingOwnAccount)>
                        <span>
                            <span class="block text-sm font-semibold text-ink-950">Usuario activo</span>
                            <span class="block text-xs text-ink-600">Puede autenticarse según los permisos de su rol.</span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">{{ $editing ? 'Restablecer contraseña' : 'Contraseña inicial' }}</h2>
                <p class="mt-1 text-sm text-ink-600">{{ $editing ? 'Déjala en blanco para conservarla. Si la cambias, usa al menos 12 caracteres, con letras y números.' : 'Debe contener al menos 12 caracteres, con letras y números.' }}</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="password">{{ $editing ? 'Nueva contraseña' : 'Contraseña *' }}</label>
                    <input class="form-input" id="password" name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" @required(! $editing)>
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirmar contraseña{{ $editing ? '' : ' *' }}</label>
                    <input class="form-input" id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" @required(! $editing)>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="btn-secondary" href="{{ route('users.index') }}">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear usuario' }}</button>
        </div>
    </form>
@endsection
