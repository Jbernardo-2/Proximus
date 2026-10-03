@extends('layouts.guest')

@section('content')
    <main class="grid min-h-dvh overflow-hidden bg-sand-50 lg:grid-cols-[minmax(28rem,0.88fr)_minmax(0,1.12fr)]">
        <section class="relative order-2 flex min-h-[calc(100dvh-15rem)] items-center justify-center overflow-hidden px-5 py-10 sm:px-10 lg:order-1 lg:min-h-dvh lg:px-12 xl:px-20">
            <div class="pointer-events-none absolute -left-32 top-1/4 size-80 rounded-full bg-mint-100/70 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 right-0 size-72 rounded-full bg-sun-500/10 blur-3xl" aria-hidden="true"></div>

            <div class="relative w-full max-w-md">
                <a href="{{ url('/') }}" class="group inline-flex items-center gap-3" aria-label="{{ config('app.name') }}">
                    <span class="relative grid size-12 place-items-center overflow-hidden rounded-2xl bg-leaf-700 text-xl font-semibold text-white shadow-lg shadow-leaf-700/20 transition group-hover:-translate-y-0.5">
                        P
                        <span class="absolute bottom-0 right-0 size-3 rounded-tl-lg bg-sun-500" aria-hidden="true"></span>
                    </span>
                    <span>
                        <span class="block text-xl font-semibold tracking-tight text-ink-950">{{ config('app.name') }}</span>
                        <span class="block text-[0.68rem] font-semibold tracking-[0.18em] text-leaf-700 uppercase">Distribución inteligente</span>
                    </span>
                </a>

                <div class="mt-10 sm:mt-12">
                    <span class="inline-flex items-center gap-2 rounded-full border border-leaf-500/15 bg-mint-50 px-3 py-1.5 text-xs font-semibold text-leaf-700">
                        <span class="size-1.5 rounded-full bg-leaf-500 shadow-[0_0_0_4px_rgba(34,162,123,0.12)]" aria-hidden="true"></span>
                        Acceso al panel de operaciones
                    </span>
                    <h1 class="mt-5 text-4xl font-semibold tracking-[-0.035em] text-ink-950 sm:text-[2.75rem] sm:leading-[1.08]">Bienvenido de nuevo</h1>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-ink-600">Ingresa tus credenciales para continuar administrando el catálogo de Proximus.</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 flex gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-700 shadow-sm" role="alert" aria-live="polite">
                        <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path stroke-linecap="round" d="M12 8v5m0 3h.01"></path>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-ink-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 8 7 5 7-5"></path>
                            </svg>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                inputmode="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                @class(['form-input pl-11', 'border-red-300' => $errors->has('email')])
                                placeholder="nombre@empresa.com"
                                @if ($errors->has('email')) aria-invalid="true" @endif
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-ink-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="10" rx="3"></rect>
                                <path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="form-input px-11"
                                placeholder="Ingresa tu contraseña"
                            >
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-ink-600 transition hover:bg-mint-50 hover:text-leaf-700"
                                data-password-toggle="password"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                            >
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
                    </div>

                    <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-ink-600">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-stone-300 text-leaf-700 focus:ring-leaf-500">
                        Mantener mi sesión iniciada
                    </label>

                    <button type="submit" class="btn-primary group w-full py-3.5 text-[0.95rem] shadow-lg shadow-leaf-700/15">
                        Iniciar sesión
                        <svg class="size-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"></path>
                        </svg>
                    </button>
                </form>

                <div class="mt-10 flex items-center justify-center gap-2 text-center text-xs text-ink-600">
                    <svg class="size-4 text-leaf-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.8 2.9 8.4 7 10 4.1-1.6 7-5.2 7-10V6l-7-3Z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 1.7 1.7 3.6-4"></path>
                    </svg>
                    Acceso exclusivo para personal autorizado
                </div>
            </div>
        </section>

        <section class="relative order-1 min-h-60 overflow-hidden bg-ink-950 lg:order-2 lg:min-h-dvh" aria-label="Distribución Proximus">
            <picture>
                <source media="(max-width: 1023px)" srcset="{{ asset('images/proximus-login-hero-mobile.webp') }}">
                <img
                    src="{{ asset('images/proximus-login-hero.webp') }}"
                    alt="Camión de distribución saliendo de una bodega hacia comercios locales"
                    class="absolute inset-0 size-full object-cover object-[center_58%] lg:object-center"
                    width="1672"
                    height="941"
                    fetchpriority="high"
                    decoding="async"
                >
            </picture>

            <div class="absolute inset-0 bg-gradient-to-b from-ink-950/25 via-transparent to-ink-950/50" aria-hidden="true"></div>
            <div class="absolute inset-x-4 top-4 sm:inset-x-6 sm:top-6 lg:inset-x-10 lg:top-10 xl:inset-x-14 xl:top-12">
                <div class="max-w-xl rounded-2xl border border-white/15 bg-ink-950/68 p-4 text-white shadow-2xl backdrop-blur-md sm:p-5 lg:rounded-3xl lg:p-7">
                    <div class="flex items-center gap-2 text-[0.68rem] font-semibold tracking-[0.18em] text-mint-100 uppercase sm:text-xs">
                        <span class="h-px w-7 bg-sun-500" aria-hidden="true"></span>
                        Bodega · Ruta · Entrega
                    </div>
                    <h2 class="mt-2 text-xl font-semibold leading-tight tracking-tight sm:text-2xl lg:mt-3 lg:text-3xl xl:text-4xl">Tu operación, siempre en movimiento.</h2>
                    <p class="mt-2 hidden max-w-lg text-sm leading-6 text-white/75 sm:block lg:mt-3 lg:text-base">Productos, precios y presentaciones organizados para que cada pedido avance por la ruta correcta.</p>
                </div>
            </div>
        </section>
    </main>
@endsection
