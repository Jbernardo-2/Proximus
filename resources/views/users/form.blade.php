@extends('layouts.app')

@php
    $editing = $managedUser->exists;
    $editingOwnAccount = $editing && $managedUser->is(auth()->user());
    $selectedRole = old('role', $managedUser->role?->value ?? 'bodeguero');
    // A new user always gets a password; when editing, it is changed only on purpose.
    $passwordOpen = ! $editing || $errors->has('password') || filled(old('password'));
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
                <p class="mt-1 text-sm text-ink-600">Con el correo y la contraseña entra a la web y a la app.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="name">Nombre completo *</label>
                    <input class="form-input" id="name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name', $managedUser->name) }}">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="email">Correo electrónico *</label>
                    <input class="form-input" id="email" name="email" type="email" required maxlength="255" autocomplete="off" value="{{ old('email', $managedUser->email) }}"
                           data-email-check="{{ route('users.email-check') }}" @if ($editing) data-email-ignore="{{ $managedUser->getKey() }}" @endif aria-describedby="email-taken">
                    <p id="email-taken" class="form-error {{ $errors->has('email') ? '' : 'hidden' }}" data-email-taken aria-live="polite">@error('email'){{ $message }}@enderror</p>
                </div>
                <div class="sm:col-span-2">
                    <span class="form-label">Rol *</span>
                    @if ($editingOwnAccount)
                        <input type="hidden" name="role" value="{{ $managedUser->role->value }}">
                    @endif
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 has-[:checked]:border-leaf-600 has-[:checked]:bg-mint-50 {{ $editingOwnAccount ? 'cursor-not-allowed opacity-60' : '' }}">
                                <input class="mt-1 size-4 text-leaf-700" type="radio" name="role" value="{{ $role->value }}" @checked($selectedRole === $role->value) @disabled($editingOwnAccount) required>
                                <span>
                                    <span class="block text-sm font-semibold text-ink-950">{{ $role->label() }}</span>
                                    <span class="block text-xs text-ink-600">{{ $role->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('role')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    @if ($editingOwnAccount)
                        <input type="hidden" name="is_active" value="1">
                    @else
                        <input type="hidden" name="is_active" value="0">
                    @endif
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-stone-200 px-4 py-2.5">
                        <input class="size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editing ? $managedUser->is_active : true)) @disabled($editingOwnAccount)>
                        <span>
                            <span class="block text-sm font-semibold text-ink-950">Usuario activo</span>
                            <span class="block text-xs text-ink-600">Puede entrar según los permisos de su rol.</span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-ink-950">{{ $editing ? 'Contraseña' : 'Contraseña inicial' }}</h2>
                    <p class="mt-1 text-sm text-ink-600">
                        {{ $editing ? 'Si la cambias, se cierran sus sesiones en la web y en los teléfonos, y entra con la nueva.' : 'Pásasela al usuario; podrá usarla en la web y en la app.' }}
                    </p>
                </div>
                @if ($editing)
                    <button type="button" class="btn-secondary {{ $passwordOpen ? 'hidden' : '' }}" data-password-open="password-fields">Cambiar contraseña</button>
                @endif
            </div>

            <div id="password-fields" class="{{ $passwordOpen ? '' : 'hidden' }} max-w-md space-y-2">
                <label class="form-label" for="password">{{ $editing ? 'Nueva contraseña' : 'Contraseña *' }}</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input class="form-input pr-11" id="password" name="password" type="password" minlength="8" maxlength="128" autocomplete="new-password"
                               value="{{ old('password') }}" @required(! $editing) @disabled(! $passwordOpen) data-password-rules>
                        <button type="button" class="absolute right-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-ink-600 transition hover:bg-mint-50 hover:text-leaf-700"
                                data-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg data-password-visible-icon class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                <circle cx="12" cy="12" r="2.5"></circle>
                            </svg>
                            <svg data-password-hidden-icon class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" d="m4 4 16 16"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.7 6.1A10 10 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M14 17.8a9.4 9.4 0 0 1-2 .2c-6 0-9.5-6-9.5-6a15 15 0 0 1 3.2-3.7"></path>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="btn-secondary" data-password-generate="password">Generar</button>
                </div>
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-600" data-password-checklist="password">
                    <li data-rule="length">8 caracteres</li>
                    <li data-rule="letter">una letra</li>
                    <li data-rule="number">un número</li>
                </ul>
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="btn-secondary" href="{{ route('users.index') }}">Cancelar</a>
            <button class="btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear usuario' }}</button>
        </div>
    </form>
@endsection
