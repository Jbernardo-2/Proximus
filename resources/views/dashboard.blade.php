@extends('layouts.app')

@section('title', 'Resumen')
@section('page-title', 'Resumen del catálogo')
@section('page-subtitle', 'Una vista rápida de la información preparada para operar.')

@section('header-actions')
    <a href="{{ route('products.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo producto</span></a>
@endsection

@section('content')
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @php
            $cards = [
                ['label' => 'Productos', 'value' => $metrics['products'], 'hint' => $metrics['active_products'].' activos', 'accent' => 'bg-leaf-700'],
                ['label' => 'Categorías', 'value' => $metrics['categories'], 'hint' => 'Familias de producto', 'accent' => 'bg-amber-500'],
                ['label' => 'Marcas', 'value' => $metrics['brands'], 'hint' => 'Marcas registradas', 'accent' => 'bg-sky-600'],
                ['label' => 'Proveedores', 'value' => $metrics['suppliers'], 'hint' => 'Fuentes de compra', 'accent' => 'bg-violet-600'],
            ];
        @endphp

        @foreach ($cards as $card)
            <article class="card relative overflow-hidden p-5 xl:col-span-1 {{ $loop->first ? 'sm:col-span-2 xl:col-span-2' : '' }}">
                <span class="absolute right-4 top-4 size-2.5 rounded-full {{ $card['accent'] }}"></span>
                <p class="text-sm font-semibold text-ink-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-black tracking-tight text-ink-950">{{ number_format($card['value']) }}</p>
                <p class="mt-1 text-xs text-ink-600">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-7 grid gap-6 xl:grid-cols-[1.45fr_0.55fr]">
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

        <aside class="card p-5">
            <p class="text-sm font-semibold text-leaf-700">Criterio de conversión</p>
            <h2 class="mt-1 text-xl font-black text-ink-950">El sistema sugiere; el usuario confirma.</h2>
            <p class="mt-3 text-sm leading-6 text-ink-600">Si se solicitan 30 unidades de una caja de 24, se muestra 1 caja + 6 unidades con sus precios. Nada se cambia de forma silenciosa.</p>
            <div class="mt-5 rounded-xl bg-mint-50 p-4 text-sm text-ink-800">
                <p class="font-semibold">Ejemplo</p>
                <div class="mt-2 flex items-center justify-between"><span>1 × Caja 24</span><span>24 u</span></div>
                <div class="mt-1 flex items-center justify-between"><span>6 × Unidad</span><span>6 u</span></div>
            </div>
        </aside>
    </section>
@endsection
