@extends('layouts.app')

@section('title', 'Bodegas')
@section('page-title', 'Bodegas')
@section('page-subtitle', 'Origen de existencias, reservas y preparación de pedidos.')
@section('header-actions')<a href="{{ route('warehouses.create') }}" class="btn-primary">＋ Nueva bodega</a>@endsection

@section('content')
    <div class="space-y-6">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm leading-6 text-sky-950"><strong>Preparado para crecer:</strong> actualmente puedes trabajar solo con la bodega principal, pero cada existencia y pedido ya queda separado por bodega para evitar una migración riesgosa después.</div>
        <section class="grid gap-4 lg:grid-cols-2">
            @foreach($warehouses as $warehouse)
                <article class="card p-5"><div class="flex items-start justify-between gap-4"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-black">{{ $warehouse->name }}</h2>@if($warehouse->is_default)<span class="rounded-full bg-mint-100 px-2.5 py-1 text-xs font-bold text-leaf-700">Predeterminada</span>@endif<x-status-badge :active="$warehouse->is_active" /></div><p class="mt-1 font-mono text-xs text-ink-600">{{ $warehouse->code }}</p></div><a class="btn-secondary" href="{{ route('warehouses.edit', $warehouse) }}">Editar</a></div>@if($warehouse->address)<p class="mt-4 whitespace-pre-line text-sm text-ink-600">{{ $warehouse->address }}</p>@endif<div class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-xl bg-stone-50 p-3"><p class="text-2xl font-black">{{ $warehouse->stocks_count }}</p><p class="text-xs text-ink-600">productos controlados</p></div><div class="rounded-xl bg-stone-50 p-3"><p class="text-2xl font-black">{{ $warehouse->orders_count }}</p><p class="text-xs text-ink-600">pedidos asignados</p></div></div></article>
            @endforeach
        </section>
    </div>
@endsection
