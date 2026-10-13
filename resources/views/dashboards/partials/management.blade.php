@php
    $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
    $money = static fn ($value): string => $currency.' '.number_format((float) $value, 2);
    $today = $dashboard['today'];
    $receivables = $dashboard['receivables'];
    $trend = $dashboard['trend'];
    $trendMaximum = max(max($trend['sold']), max($trend['collected']), 1);
    $hasOverdue = bccomp($receivables['overdue'], '0', 4) > 0;
@endphp

<div class="space-y-6" data-management-dashboard>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card p-5">
            <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-600 uppercase"><span class="size-2 rounded-full bg-[#188365]" aria-hidden="true"></span>Vendido hoy</p>
            <p class="mt-2 text-2xl font-black text-ink-950">{{ $money($today['sold']) }}</p>
            <p class="mt-1 text-xs text-ink-600">{{ $today['deliveries'] }} {{ $today['deliveries'] === 1 ? 'entrega' : 'entregas' }}</p>
        </article>
        <article class="card p-5">
            <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-600 uppercase"><span class="size-2 rounded-full bg-[#2f6fd0]" aria-hidden="true"></span>Cobrado hoy</p>
            <p class="mt-2 text-2xl font-black text-ink-950">{{ $money($today['collected']) }}</p>
            <p class="mt-1 text-xs text-ink-600">Entregas {{ $money($today['collected_on_delivery']) }} · Abonos {{ $money($today['collected_on_account']) }}</p>
        </article>
        <article class="card p-5">
            <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-600 uppercase"><span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>Fiado hoy</p>
            <p class="mt-2 text-2xl font-black text-ink-950">{{ $money($today['credit']) }}</p>
            <p class="mt-1 text-xs text-ink-600">{{ $today['credit_deliveries'] }} {{ $today['credit_deliveries'] === 1 ? 'entrega' : 'entregas' }} a crédito</p>
        </article>
        <a class="card block p-5 transition hover:border-leaf-600" href="{{ route('credits.index') }}">
            <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-600 uppercase"><span class="size-2 rounded-full {{ $hasOverdue ? 'bg-red-500' : 'bg-stone-300' }}" aria-hidden="true"></span>Por cobrar</p>
            <p class="mt-2 text-2xl font-black text-ink-950">{{ $money($receivables['outstanding']) }}</p>
            <p class="mt-1 text-xs {{ $hasOverdue ? 'font-semibold text-red-700' : 'text-ink-600' }}">
                @if($hasOverdue)
                    Vencido {{ $money($receivables['overdue']) }} · {{ $receivables['overdue_customers'] }} {{ $receivables['overdue_customers'] === 1 ? 'cliente' : 'clientes' }}
                @else
                    Sin saldo vencido
                @endif
            </p>
        </a>
    </section>

    <section class="card grid overflow-hidden divide-y divide-stone-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
        @foreach($dashboard['pending'] as $pending)
            @php($pendingTone = match (true) {
                $pending['count'] === 0 => 'hover:bg-stone-50',
                $pending['critical'] => 'bg-red-50 hover:bg-red-100',
                default => 'bg-amber-50 hover:bg-amber-100',
            })
            <a class="flex items-center justify-between gap-3 px-5 py-4 transition {{ $pendingTone }}" href="{{ $pending['url'] }}">
                <span class="text-sm font-semibold {{ $pending['count'] > 0 ? 'text-ink-950' : 'text-ink-600' }}">{{ $pending['label'] }}</span>
                <span class="text-xl font-black {{ $pending['count'] > 0 ? 'text-ink-950' : 'text-stone-300' }}">{{ $pending['count'] }}</span>
            </a>
        @endforeach
    </section>

    <div class="grid items-start gap-6 xl:grid-cols-2">
        <div class="space-y-6">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                <h2 class="font-bold text-ink-950">Reparto</h2>
                <a class="text-sm font-semibold text-leaf-700 hover:underline" href="{{ route('delivery-runs.index') }}">Ver jornadas</a>
            </header>
            <div class="divide-y divide-stone-100">
                @forelse($dashboard['runs'] as $run)
                    <a class="block px-5 py-4 transition hover:bg-stone-50" href="{{ route('delivery-runs.show', $run) }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 truncate font-semibold text-ink-950">{{ $run->driver_name }} <span class="font-normal text-ink-600">· {{ $run->run_number }} · {{ $run->scheduled_date->format('d/m') }}</span></p>
                            <x-delivery-run-status-badge :status="$run->status" />
                        </div>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true"><div class="h-full rounded-full bg-leaf-600" style="width: {{ round(($run->completed_stops_count / max($run->stops_count, 1)) * 100) }}%"></div></div>
                        <p class="mt-2 text-xs text-ink-600">{{ $run->completed_stops_count }} de {{ $run->stops_count }} paradas · Cobrado {{ $money($run->collected_total) }} · Crédito {{ $money($run->credit_total) }}</p>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-600">Sin jornadas abiertas.</p>
                @endforelse
            </div>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                <h2 class="font-bold text-ink-950">Pedidos sin asignar</h2>
                <a class="text-sm font-semibold text-leaf-700 hover:underline" href="{{ route('orders.index', ['status' => \App\OrderStatus::Confirmed->value]) }}">Ver todos</a>
            </header>
            <div class="divide-y divide-stone-100">
                @forelse($dashboard['unassignedOrders'] as $order)
                    <a class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-stone-50" href="{{ route('orders.show', $order) }}">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-ink-950">{{ $order->customer_name }}</p><p class="text-xs text-ink-600">{{ $order->order_number }} · {{ $order->route_name ?: 'Fuera de ruta' }} · {{ $order->confirmed_at?->diffForHumans() }}</p></div>
                        <p class="shrink-0 text-sm font-bold text-ink-950">{{ $money($order->total) }}</p>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-600">Sin pedidos por asignar.</p>
                @endforelse
            </div>
        </section>
        </div>

        <div class="space-y-6">
        <section class="card p-5" data-trend-chart data-default-readout="Últimos 7 días">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-bold text-ink-950">Vendido y cobrado</h2>
                <div class="flex items-center gap-4 text-xs text-ink-600">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#188365]"></span>Vendido</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#2f6fd0]"></span>Cobrado</span>
                </div>
            </header>
            <p class="mt-2 text-xs text-ink-600" data-trend-readout aria-live="polite">Últimos 7 días</p>
            <div class="mt-4 grid h-44 grid-cols-7 gap-2 border-b border-stone-200" aria-hidden="true">
                @foreach($trend['labels'] as $index => $label)
                    <div class="flex h-full items-end justify-center gap-0.5 rounded-t-md transition hover:bg-stone-50" data-trend-day="{{ ucfirst($label) }}: vendido {{ $money($trend['sold'][$index]) }} · cobrado {{ $money($trend['collected'][$index]) }}">
                        <div class="w-full max-w-5 rounded-t bg-[#188365]" style="height: {{ round(($trend['sold'][$index] / $trendMaximum) * 100, 2) }}%"></div>
                        <div class="w-full max-w-5 rounded-t bg-[#2f6fd0]" style="height: {{ round(($trend['collected'][$index] / $trendMaximum) * 100, 2) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 grid grid-cols-7 gap-2 text-center text-[11px] text-ink-600" aria-hidden="true">
                @foreach($trend['labels'] as $label)
                    <span>{{ ucfirst($label) }}</span>
                @endforeach
            </div>
            <table class="sr-only">
                <caption>Vendido y cobrado por día, últimos 7 días</caption>
                <thead><tr><th>Día</th><th>Vendido</th><th>Cobrado</th></tr></thead>
                <tbody>
                    @foreach($trend['labels'] as $index => $label)
                        <tr><td>{{ $label }}</td><td>{{ $money($trend['sold'][$index]) }}</td><td>{{ $money($trend['collected'][$index]) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                <h2 class="font-bold text-ink-950">Cartera vencida</h2>
                <a class="text-sm font-semibold text-leaf-700 hover:underline" href="{{ route('credits.index') }}">Ver cartera</a>
            </header>
            <div class="divide-y divide-stone-100">
                @forelse($dashboard['overdueReceivables'] as $receivable)
                    @php($daysOverdue = (int) $receivable->due_date->diffInDays(today()))
                    <a class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-stone-50" href="{{ route('customers.show', $receivable->customer) }}#customer-credit">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-ink-950">{{ $receivable->customer->business_name }}</p><p class="text-xs text-ink-600">{{ $receivable->document_number }} · {{ $daysOverdue }} {{ $daysOverdue === 1 ? 'día' : 'días' }} de atraso</p></div>
                        <p class="shrink-0 text-sm font-bold text-red-700">{{ $money($receivable->outstanding_amount) }}</p>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-600">Sin saldos vencidos.</p>
                @endforelse
            </div>
        </section>
        </div>
    </div>
</div>
