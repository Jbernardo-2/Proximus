@extends('layouts.app')

@section('title', 'Cartera de clientes')
@section('page-title', 'Cartera de clientes')
@section('page-subtitle', 'Saldos, vencimientos y disponibilidad de crédito en un solo lugar')

@section('content')
    @php
        $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
    @endphp

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <div class="card p-5">
                <p class="text-xs font-bold tracking-[0.12em] text-ink-600 uppercase">Cartera pendiente</p>
                <p class="mt-2 text-3xl font-black text-ink-950">{{ $currency }} {{ number_format((float) $portfolioBalance, 2) }}</p>
                <p class="mt-1 text-sm text-ink-600">Saldo por recuperar de todos los clientes.</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold tracking-[0.12em] text-amber-800 uppercase">Cartera vencida</p>
                <p class="mt-2 text-3xl font-black text-amber-900">{{ $currency }} {{ number_format((float) $overdueBalance, 2) }}</p>
                <p class="mt-1 text-sm text-ink-600">Documentos cuya fecha de pago ya pasó.</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-bold tracking-[0.12em] text-leaf-700 uppercase">Clientes con saldo</p>
                <p class="mt-2 text-3xl font-black text-ink-950">{{ number_format($debtorsCount) }}</p>
                <p class="mt-1 text-sm text-ink-600">Clientes que requieren seguimiento de cobro.</p>
            </div>
        </section>

        <section class="card overflow-hidden">
            <form method="GET" action="{{ route('credits.index') }}" class="grid gap-4 border-b border-stone-100 p-5 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-end">
                <div>
                    <label class="form-label" for="credit-search">Buscar cliente</label>
                    <input class="form-input" id="credit-search" name="search" value="{{ $search }}" placeholder="Nombre, código o contacto">
                </div>
                <div>
                    <label class="form-label" for="credit-status">Estado de crédito</label>
                    <select class="form-input" id="credit-status" name="status">
                        <option value="">Todos</option>
                        @foreach(\App\CustomerCreditStatus::cases() as $creditStatus)
                            <option value="{{ $creditStatus->value }}" @selected($selectedStatus === $creditStatus->value)>{{ $creditStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="flex min-h-11 items-center gap-2 rounded-xl border border-stone-200 px-3 text-sm font-semibold text-ink-800">
                        <input name="overdue" type="checkbox" value="1" @checked($overdueOnly)>
                        Solo vencidos
                    </label>
                    <button class="btn-primary" type="submit">Filtrar</button>
                    @if($search !== '' || $selectedStatus !== '' || $overdueOnly)
                        <a class="btn-secondary" href="{{ route('credits.index') }}">Limpiar</a>
                    @endif
                </div>
            </form>

            @if($customers->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Sin resultados" description="No hay clientes que coincidan con los filtros seleccionados." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px]">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="table-heading">Cliente</th>
                                <th class="table-heading">Política</th>
                                <th class="table-heading text-right">Límite</th>
                                <th class="table-heading text-right">Por cobrar</th>
                                <th class="table-heading text-right">Comprometido</th>
                                <th class="table-heading text-right">Disponible</th>
                                <th class="table-heading text-right">Vencido</th>
                                <th class="table-heading text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                @php
                                    $limit = (string) ($customer->creditProfile?->credit_limit ?? '0');
                                    $outstanding = (string) ($customer->outstanding_balance ?? '0');
                                    $committed = (string) ($customer->committed_credit ?? '0');
                                    $available = bcsub($limit, bcadd($outstanding, $committed, 4), 4);
                                    $overdue = (string) ($customer->overdue_balance ?? '0');
                                @endphp
                                <tr>
                                    <td class="table-cell">
                                        <p class="font-bold text-ink-950">{{ $customer->business_name }}</p>
                                        <p class="text-xs text-ink-600">{{ $customer->code }} · {{ $customer->contact_name ?: 'Sin contacto' }}</p>
                                    </td>
                                    <td class="table-cell">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $customer->creditProfile?->status->allowsNewCredit() ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-700' }}">
                                            {{ $customer->creditProfile?->status->label() ?? 'Sin crédito' }}
                                        </span>
                                    </td>
                                    <td class="table-cell text-right font-semibold">{{ $currency }} {{ number_format((float) $limit, 2) }}</td>
                                    <td class="table-cell text-right font-black text-amber-900">{{ $currency }} {{ number_format((float) $outstanding, 2) }}</td>
                                    <td class="table-cell text-right">{{ $currency }} {{ number_format((float) $committed, 2) }}</td>
                                    <td class="table-cell text-right font-black {{ bccomp($available, '0', 4) < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $currency }} {{ number_format((float) $available, 2) }}</td>
                                    <td class="table-cell text-right font-black {{ bccomp($overdue, '0', 4) > 0 ? 'text-red-700' : 'text-stone-400' }}">{{ $currency }} {{ number_format((float) $overdue, 2) }}</td>
                                    <td class="table-cell text-right"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('customers.show', $customer) }}#credit">Ver cuenta</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-stone-100 p-5">{{ $customers->links() }}</div>
            @endif
        </section>
    </div>
@endsection
