@extends('layouts.app')

@section('title', 'Pedidos')
@section('page-title', 'Preventa y pedidos')
@section('page-subtitle', 'Controla borradores, confirmaciones y pedidos listos para bodega.')
@section('header-actions')
    @can('manage-orders')
        <a href="{{ route('orders.create') }}" class="btn-primary">＋ <span class="hidden sm:inline">Nuevo pedido</span></a>
    @endcan
@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-stone-100 p-4">
            <form method="GET" class="grid gap-3 xl:grid-cols-[minmax(14rem,1fr)_10rem_10rem_10rem_10rem_auto]">
                <div>
                    <label class="sr-only" for="search">Buscar pedido</label>
                    <input class="form-input" id="search" type="search" name="search" value="{{ $search }}" placeholder="Número, cliente o ruta…">
                </div>
                <div>
                    <label class="sr-only" for="status">Estado</label>
                    <select class="form-input" id="status" name="status">
                        <option value="">Todos los estados</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="payment_term">Pago</label>
                    <select class="form-input" id="payment_term" name="payment_term">
                        <option value="">Todo pago</option>
                        @foreach ($paymentTerms as $term)
                            <option value="{{ $term->value }}" @selected($selectedPaymentTerm === $term->value)>{{ $term->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="date_from">Desde</label>
                    <input class="form-input" id="date_from" type="date" name="date_from" value="{{ $dateFrom }}" title="Fecha desde">
                </div>
                <div>
                    <label class="sr-only" for="date_to">Hasta</label>
                    <input class="form-input" id="date_to" type="date" name="date_to" value="{{ $dateTo }}" title="Fecha hasta">
                </div>
                <div class="flex gap-2">
                    <button class="btn-secondary flex-1" type="submit">Filtrar</button>
                    @if ($search !== '' || $selectedStatus !== '' || $selectedPaymentTerm !== '' || $dateFrom !== '' || $dateTo !== '')
                        <a class="btn-secondary px-3" href="{{ route('orders.index') }}" aria-label="Limpiar filtros">×</a>
                    @endif
                </div>
            </form>
            <p class="mt-3 text-sm text-ink-600">{{ $orders->total() }} pedidos encontrados</p>
        </div>

        @if ($orders->isEmpty())
            <div class="p-6">
                <x-empty-state title="No hay pedidos" description="Crea un borrador desde una visita de ruta para comenzar la preventa.">
                    @can('manage-orders')
                        <x-slot:action><a href="{{ route('orders.create') }}" class="btn-primary">Crear pedido</a></x-slot:action>
                    @endcan
                </x-empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead class="bg-stone-50">
                        <tr><th class="table-heading">Pedido</th><th class="table-heading">Cliente</th><th class="table-heading">Ruta / preventista</th><th class="table-heading">Pago</th><th class="table-heading text-right">Total</th><th class="table-heading text-right">Acción</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr class="hover:bg-mint-50/30">
                                <td class="table-cell"><p class="font-bold text-ink-950">{{ $order->order_number }}</p><div class="mt-1 flex items-center gap-2"><x-order-status-badge :status="$order->status" /><span class="text-xs text-ink-600">{{ $order->order_date->format('d/m/Y') }}</span></div></td>
                                <td class="table-cell"><p class="font-semibold text-ink-950">{{ $order->customer_name }}</p><p class="text-xs text-ink-600">{{ $order->customer_code }} · {{ $order->items_count }} líneas</p></td>
                                <td class="table-cell"><p class="font-semibold text-ink-950">{{ $order->route_name ?: 'Fuera de ruta' }}</p><p class="text-xs text-ink-600">{{ $order->salesperson_name }}</p></td>
                                <td class="table-cell">{{ $order->payment_term->label() }}</td>
                                <td class="table-cell text-right"><p class="font-black text-ink-950">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p></td>
                                <td class="table-cell text-right"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('orders.show', $order) }}">Ver pedido</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
