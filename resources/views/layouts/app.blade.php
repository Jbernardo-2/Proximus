<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-app-icons />
        <title>@yield('title', 'Catálogo') · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="hidden min-h-screen bg-ink-950 px-4 py-6 lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
                <a href="{{ route('dashboard') }}" class="mb-4 block rounded-xl bg-white px-3 py-2 shadow-sm transition hover:-translate-y-0.5" aria-label="Ir al inicio de Proximus">
                    <img src="{{ asset('images/proximus-logo-horizontal.svg') }}" alt="Proximus" class="h-auto w-full" width="752" height="180">
                </a>

                <div class="mb-5 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-3 text-white">
                    @if ($companyProfile->logo_path)
                        <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-white p-1">
                            <img src="{{ $companyProfile->logoUrl() }}" alt="" class="size-full object-contain">
                        </span>
                    @else
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white/10 text-sm font-black">{{ str($companyProfile->commercial_name)->substr(0, 1)->upper() }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block text-[10px] font-semibold tracking-[0.16em] text-stone-400 uppercase">Empresa usuaria</span>
                        <span class="block truncate text-sm font-semibold">{{ $companyProfile->commercial_name }}</span>
                        @if ($companyProfile->slogan)
                            <span class="block truncate text-xs text-stone-400">{{ $companyProfile->slogan }}</span>
                        @endif
                    </span>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto pb-4" aria-label="Navegación principal">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        <span class="text-base">⌂</span> Resumen
                    </a>
                    @if (auth()->user()->can('manage-customers') || auth()->user()->can('view-customer-credit') || auth()->user()->can('view-routes') || auth()->user()->can('view-orders') || auth()->user()->can('view-deliveries') || auth()->user()->can('view-route-reports') || auth()->user()->can('view-reports') || auth()->user()->can('view-inventory'))
                        <p class="px-3 pb-1 pt-6 text-[11px] font-semibold tracking-[0.16em] text-stone-500 uppercase">Operación</p>
                        @can('manage-customers')
                            <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">♧</span> Clientes
                            </a>
                        @endcan
                        @can('view-customer-credit')
                            <a href="{{ route('credits.index') }}" class="nav-link {{ request()->routeIs('credits.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">$</span> Cartera
                            </a>
                        @endcan
                        @can('view-routes')
                            <a href="{{ route('routes.index') }}" class="nav-link {{ request()->routeIs('routes.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">⌁</span> Rutas
                            </a>
                        @endcan
                        @can('view-orders')
                            <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">▤</span> Pedidos
                            </a>
                        @endcan
                        @can('view-deliveries')
                            <a href="{{ route('delivery-runs.index') }}" class="nav-link {{ request()->routeIs('delivery-runs.*', 'vehicles.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">▰</span> Reparto
                            </a>
                        @endcan
                        @can('view-inventory')
                            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*', 'inventory-*', 'warehouses.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">▥</span> Inventario
                            </a>
                        @endcan
                        @can('view-reports')
                            <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">▥</span> Reportes
                            </a>
                        @endcan
                        @can('view-route-reports')
                            <a href="{{ route('route-reports.index') }}" class="nav-link {{ request()->routeIs('route-reports.*') ? 'nav-link-active' : '' }}">
                                <span class="text-base">▤</span> Reportes de ruta
                            </a>
                        @endcan
                    @endif
                    @can('manage-catalog')
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
                    @endcan
                    @if (auth()->user()->can('manage-users') || auth()->user()->can('manage-company-profile'))
                        <p class="px-3 pb-1 pt-6 text-[11px] font-semibold tracking-[0.16em] text-stone-500 uppercase">Administración</p>
                    @endif
                    @can('manage-users')
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}">
                            <span class="text-base">♙</span> Usuarios
                        </a>
                    @endcan
                    @can('manage-company-profile')
                        <a href="{{ route('company.edit') }}" class="nav-link {{ request()->routeIs('company.*') ? 'nav-link-active' : '' }}">
                            <span class="text-base">▣</span> Empresa
                        </a>
                    @endcan
                </nav>

                <div class="mt-4 rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-stone-300">
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
                            <x-notification-center :summary="$notificationSummary" />
                            <span class="hidden size-9 place-items-center rounded-full bg-mint-100 text-sm font-bold text-leaf-700 sm:grid">
                                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                            </span>
                        </div>
                    </div>
                    <nav data-mobile-menu class="mx-auto mt-3 hidden max-w-7xl grid-cols-2 gap-2 rounded-xl bg-ink-950 p-3 text-sm text-white lg:hidden">
                        <a href="{{ route('dashboard') }}" class="col-span-2 flex items-center justify-center rounded-lg bg-white px-3 py-2" aria-label="Ir al inicio de Proximus">
                            <img src="{{ asset('images/proximus-logo-horizontal.svg') }}" alt="Proximus" class="h-8 w-auto max-w-full" width="752" height="180">
                        </a>
                        <div class="col-span-2 flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                            @if ($companyProfile->logo_path)
                                <img src="{{ $companyProfile->logoUrl() }}" alt="" class="size-8 rounded-md bg-white object-contain p-0.5">
                            @endif
                            <span class="min-w-0">
                                <span class="block text-[10px] font-semibold tracking-wide text-stone-400 uppercase">Empresa usuaria</span>
                                <span class="block truncate font-semibold">{{ $companyProfile->commercial_name }}</span>
                            </span>
                        </div>
                        <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('dashboard') }}">Resumen</a>
                        @can('manage-customers')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('customers.index') }}">Clientes</a>
                        @endcan
                        @can('view-customer-credit')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('credits.index') }}">Cartera</a>
                        @endcan
                        @can('view-routes')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('routes.index') }}">Rutas</a>
                        @endcan
                        @can('view-orders')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('orders.index') }}">Pedidos</a>
                        @endcan
                        @can('view-deliveries')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('delivery-runs.index') }}">Reparto</a>
                        @endcan
                        @can('view-inventory')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('inventory.index') }}">Inventario</a>
                        @endcan
                        @can('view-reports')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('reports.index') }}">Reportes</a>
                        @endcan
                        @can('view-route-reports')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('route-reports.index') }}">Reportes de ruta</a>
                        @endcan
                        @can('manage-catalog')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('products.index') }}">Productos</a>
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('categories.index') }}">Categorías</a>
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('brands.index') }}">Marcas</a>
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('measurement-units.index') }}">Unidades</a>
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('suppliers.index') }}">Proveedores</a>
                        @endcan
                        @can('manage-users')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('users.index') }}">Usuarios</a>
                        @endcan
                        @can('manage-company-profile')
                            <a class="rounded-lg px-3 py-2 hover:bg-white/10" href="{{ route('company.edit') }}">Empresa</a>
                        @endcan
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
