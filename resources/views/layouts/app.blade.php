<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Catálogo') · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="hidden min-h-screen bg-ink-950 px-4 py-6 lg:sticky lg:top-0 lg:block lg:h-screen">
                <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-3 px-2 text-white">
                    <span class="grid size-10 place-items-center rounded-xl bg-leaf-500 text-lg font-black">P</span>
                    <span>
                        <span class="block text-lg font-bold leading-5">{{ config('app.name') }}</span>
                        <span class="text-xs text-stone-400">Catálogo de distribución</span>
                    </span>
                </a>

                <nav class="space-y-1" aria-label="Navegación principal">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        <span class="text-base">⌂</span> Resumen
                    </a>
                    <p class="px-3 pb-1 pt-6 text-[11px] font-semibold tracking-[0.16em] text-stone-500 uppercase">Catálogo</p>
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'nav-link-active' : '' }}">
                        <span class="text-base">▦</span> Productos
                    </a>
                    <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'nav-link-active' : '' }}">
                        <span class="text-base">◇</span> Categorías
                    </a>
                    <a href="{{ route('brands.index') }}" class="nav-link {{ request()->routeIs('brands.*') ? 'nav-link-active' : '' }}">
                        <span class="text-base">◉</span> Marcas
                    </a>
                    <a href="{{ route('measurement-units.index') }}" class="nav-link {{ request()->routeIs('measurement-units.*') ? 'nav-link-active' : '' }}">
                        <span class="text-base">↔</span> Unidades
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'nav-link-active' : '' }}">
                        <span class="text-base">▱</span> Proveedores
                    </a>
                </nav>

                <div class="absolute bottom-5 left-4 right-4 rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-stone-300">
                    <p class="truncate font-semibold text-white">{{ auth()->user()->name }}</p>
                    <p class="mb-3 text-xs text-stone-400">{{ auth()->user()->role->label() }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-xs font-semibold text-stone-300 hover:text-white">Cerrar sesión →</button>
                    </form>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="border-b border-stone-200 bg-white/90 px-4 py-3 backdrop-blur lg:px-8">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" data-mobile-menu-button class="grid size-10 place-items-center rounded-xl border border-stone-200 text-xl lg:hidden" aria-label="Abrir navegación">☰</button>
                            <div class="min-w-0">
                                <h1 class="truncate text-lg font-bold text-ink-950 sm:text-xl">@yield('page-title', 'Catálogo')</h1>
                                <p class="hidden text-sm text-ink-600 sm:block">@yield('page-subtitle')</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @yield('header-actions')
                            <span class="hidden size-9 place-items-center rounded-full bg-mint-100 text-sm font-bold text-leaf-700 sm:grid">
                                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                            </span>
                        </div>
                    </div>
                    <nav data-mobile-menu class="mx-auto mt-3 hidden max-w-7xl grid-cols-2 gap-2 rounded-xl bg-ink-950 p-3 text-sm text-white lg:hidden">
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('dashboard') }}">Resumen</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('products.index') }}">Productos</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('categories.index') }}">Categorías</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('brands.index') }}">Marcas</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('measurement-units.index') }}">Unidades</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('suppliers.index') }}">Proveedores</a>
                    </nav>
                </header>

                <main class="px-4 py-6 lg:px-8 lg:py-8">
                    <div class="mx-auto max-w-7xl">
                        <x-flash />
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
