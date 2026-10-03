@extends('layouts.guest')

@section('content')
    <main class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden overflow-hidden bg-ink-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-32 -top-24 size-96 rounded-full bg-leaf-500/20 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-sun-500/12 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-leaf-500 text-xl font-black">P</span>
                <span class="text-xl font-bold">{{ config('app.name') }}</span>
            </div>

            <div class="relative max-w-xl">
                <p class="mb-5 text-sm font-semibold tracking-[0.18em] text-leaf-500 uppercase">Control de catálogo</p>
                <h1 class="text-4xl font-black leading-tight xl:text-5xl">Cada producto, presentación y precio en su lugar.</h1>
                <p class="mt-5 max-w-lg text-lg leading-8 text-stone-300">Una base clara para preparar pedidos, vender por unidad o caja y crecer hacia la operación de reparto.</p>

                <div class="mt-10 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <span class="text-2xl font-black text-leaf-500">01</span>
                        <p class="mt-2 text-sm text-stone-300">Presentaciones flexibles</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <span class="text-2xl font-black text-leaf-500">02</span>
                        <p class="mt-2 text-sm text-stone-300">Precios por volumen</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <span class="text-2xl font-black text-leaf-500">03</span>
                        <p class="mt-2 text-sm text-stone-300">Costos por proveedor</p>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-stone-500">Plataforma interna de distribución</p>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-xl bg-leaf-700 text-lg font-black text-white">P</span>
                    <span class="text-xl font-bold">{{ config('app.name') }}</span>
                </div>

                <p class="text-sm font-semibold text-leaf-700">Bienvenido</p>
                <h2 class="mt-1 text-3xl font-black tracking-tight text-ink-950">Inicia sesión</h2>
                <p class="mt-2 text-sm leading-6 text-ink-600">Ingresa con el usuario habilitado para administrar el catálogo.</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus class="form-input" placeholder="nombre@empresa.com">
                    </div>
                    <div>
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="form-input" placeholder="••••••••">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-600">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-stone-300 text-leaf-700 focus:ring-leaf-500">
                        Mantener mi sesión iniciada
                    </label>
                    <button type="submit" class="btn-primary w-full py-3">Entrar al panel <span aria-hidden="true">→</span></button>
                </form>
            </div>
        </section>
    </main>
@endsection
