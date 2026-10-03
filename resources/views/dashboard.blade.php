@extends('layouts.app')

@section('title', 'Resumen')
@section('page-title', 'Resumen de la operación')
@section('page-subtitle', 'Clientes, rutas y catálogo preparados para avanzar.')

@section('header-actions')
    @can('manage-customers')
        <a href="{{ route('customers.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo cliente</span></a>
    @elsecan('manage-catalog')
        <a href="{{ route('products.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo producto</span></a>
    @endcan
@endsection

@section('content')
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [];

            if (auth()->user()->canManageCustomers()) {
                $cards[] = ['label' => 'Clientes', 'value' => $metrics['customers'], 'hint' => $metrics['active_customers'].' activos', 'accent' => 'bg-leaf-700'];
            }

            if (auth()->user()->canManageRoutes()) {
                $cards[] = ['label' => 'Rutas', 'value' => $metrics['routes'], 'hint' => $metrics['active_routes'].' activas', 'accent' => 'bg-amber-500'];
            }

            if (auth()->user()->canManageCatalog()) {
                $cards[] = ['label' => 'Productos', 'value' => $metrics['products'], 'hint' => $metrics['active_products'].' activos', 'accent' => 'bg-sky-600'];
                $cards[] = ['label' => 'Proveedores', 'value' => $metrics['suppliers'], 'hint' => $metrics['categories'].' categorías · '.$metrics['brands'].' marcas', 'accent' => 'bg-violet-600'];
            }
        @endphp

        @foreach ($cards as $card)
            <article class="card relative overflow-hidden p-5">
                <span class="absolute right-4 top-4 size-2.5 rounded-full {{ $card['accent'] }}"></span>
                <p class="text-sm font-semibold text-ink-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-black tracking-tight text-ink-950">{{ number_format($card['value']) }}</p>
                <p class="mt-1 text-xs text-ink-600">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-7 grid gap-6 {{ auth()->user()->canManageCustomers() && auth()->user()->canManageCatalog() ? 'xl:grid-cols-2' : '' }}">
        @can('manage-customers')
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4">
                    <div><h2 class="font-bold text-ink-950">Clientes recientes</h2><p class="text-sm text-ink-600">Últimos negocios registrados</p></div>
                    <a href="{{ route('customers.index') }}" class="text-sm font-semibold text-leaf-700 hover:underline">Ver todos</a>
                </div>
                @if ($recentCustomers->isEmpty())
                    <div class="p-6"><x-empty-state title="Aún no hay clientes" description="Registra el primer negocio para comenzar a organizar las rutas."><x-slot:action><a href="{{ route('customers.create') }}" class="btn-primary">Crear cliente</a></x-slot:action></x-empty-state></div>
                @else
                    <div class="divide-y divide-stone-100">
                        @foreach ($recentCustomers as $customer)
                            <a href="{{ route('customers.show', $customer) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60"><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ $customer->business_name }}</p><p class="truncate text-sm text-ink-600">{{ $customer->code }} · {{ $customer->business_type ?: 'Sin tipo' }}</p></div><div class="shrink-0 text-right"><p class="font-semibold text-ink-950">{{ $customer->route_stops_count }}</p><p class="text-xs text-ink-600">visitas</p></div></a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endcan

        @can('manage-catalog')
            <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4">
                <div>
                    <h2 class="font-bold text-ink-950">Productos recientes</h2>
                    <p class="text-sm text-ink-600">Últimos registros del catálogo</p>
                </div>
                <a href="{{ route('products.index') }}" class="text-sm font-semibold text-leaf-700 hover:underline">Ver todos</a>
            </div>
            @if ($recentProducts->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Aún no hay productos" description="Registra el primer producto y su presentación base para comenzar.">
                        <x-slot:action><a href="{{ route('products.create') }}" class="btn-primary">Crear producto</a></x-slot:action>
                    </x-empty-state>
                </div>
            @else
                <div class="divide-y divide-stone-100">
                    @foreach ($recentProducts as $product)
                        <a href="{{ route('products.show', $product) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-mint-50/60">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink-950">{{ $product->name }}</p>
                                <p class="truncate text-sm text-ink-600">{{ $product->sku }} · {{ $product->category->name }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold text-ink-950">{{ number_format((float) $product->basePresentation?->sale_price, 2) }}</p>
                                <p class="text-xs text-ink-600">{{ $product->basePresentation?->name }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
            </div>
        @endcan
    </section>
@endsection
