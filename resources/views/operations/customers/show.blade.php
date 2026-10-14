@extends('layouts.app')

@section('title', $customer->business_name)
@section('page-title', $customer->business_name)
@section('page-subtitle', $customer->code.' · '.($customer->business_type ?: 'Tipo de negocio sin definir'))
@section('header-actions')
    <a href="{{ route('customers.edit', $customer) }}" class="btn-primary">Editar cliente</a>
@endsection

@section('content')
    @php
        $currency = mb_strtoupper((string) config('proximus.currency', 'HNL'));
    @endphp
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="card p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Ficha del cliente</p>
                        <h2 class="mt-2 text-xl font-black text-ink-950">{{ $customer->business_name }}</h2>
                    </div>
                    <x-status-badge :active="$customer->is_active" />
                </div>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Contacto</dt><dd class="mt-1 font-semibold text-ink-950">{{ $customer->contact_name ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Teléfono</dt><dd class="mt-1 text-ink-800">{{ $customer->phone ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">WhatsApp</dt><dd class="mt-1 text-ink-800">{{ $customer->whatsapp ?: 'Sin registrar' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-ink-600 uppercase">Correo</dt><dd class="mt-1 break-all text-ink-800">{{ $customer->email ?: 'Sin registrar' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Dirección</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $customer->address }}</dd></div>
                    @if ($customer->reference)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Referencia</dt><dd class="mt-1 whitespace-pre-line text-ink-800">{{ $customer->reference }}</dd></div>
                    @endif
                    @if ($customer->latitude !== null && $customer->longitude !== null)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold text-ink-600 uppercase">Coordenadas</dt><dd class="mt-1 font-mono text-sm text-ink-800">{{ $customer->latitude }}, {{ $customer->longitude }}</dd></div>
                    @endif
                </dl>
                @if ($customer->notes)
                    <div class="mt-6 rounded-xl bg-sand-50 p-4"><p class="text-xs font-semibold text-ink-600 uppercase">Notas internas</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-800">{{ $customer->notes }}</p></div>
                @endif
            </section>

            <section class="card overflow-hidden" id="credit">
                <header class="border-b border-stone-100 p-5 sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Política de crédito</p>
                            <h2 class="mt-2 text-xl font-black text-ink-950">Límite, plazo y disponibilidad</h2>
                        </div>
                        <span class="inline-flex rounded-full px-3 py-1.5 text-sm font-bold {{ $creditSummary['profile_status']->allowsNewCredit() ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-700' }}">
                            {{ $creditSummary['profile_status']->label() }}
                        </span>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Límite</p><p class="mt-1 text-lg font-black">{{ $currency }} {{ number_format((float) $creditSummary['credit_limit'], 2) }}</p></div>
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Por cobrar</p><p class="mt-1 text-lg font-black text-amber-900">{{ $currency }} {{ number_format((float) $creditSummary['receivable_balance'], 2) }}</p></div>
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Pedidos comprometidos</p><p class="mt-1 text-lg font-black">{{ $currency }} {{ number_format((float) $creditSummary['committed_credit'], 2) }}</p></div>
                        <div class="rounded-xl {{ bccomp($creditSummary['available_credit'], '0', 4) < 0 ? 'bg-red-50' : 'bg-emerald-50' }} p-4"><p class="text-xs font-bold text-ink-600 uppercase">Disponible</p><p class="mt-1 text-lg font-black {{ bccomp($creditSummary['available_credit'], '0', 4) < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $currency }} {{ number_format((float) $creditSummary['available_credit'], 2) }}</p></div>
                        <div class="rounded-xl {{ bccomp($creditSummary['overdue_balance'], '0', 4) > 0 ? 'bg-red-50' : 'bg-stone-50' }} p-4"><p class="text-xs font-bold text-ink-600 uppercase">Vencido</p><p class="mt-1 text-lg font-black {{ bccomp($creditSummary['overdue_balance'], '0', 4) > 0 ? 'text-red-700' : '' }}">{{ $currency }} {{ number_format((float) $creditSummary['overdue_balance'], 2) }}</p></div>
                    </div>
                    @if(bccomp($creditSummary['reconciliation_difference'], '0', 4) !== 0)
                        <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700">La cuenta contable y los documentos por cobrar tienen una diferencia de {{ $currency }} {{ number_format((float) $creditSummary['reconciliation_difference'], 2) }}. Revisa el historial antes de autorizar más crédito.</p>
                    @endif
                    <p class="mt-4 text-sm text-ink-600">Plazo configurado: <strong>{{ $customer->creditProfile?->term_days ?? 0 }} días</strong>@if(($customer->creditProfile?->grace_days ?? 0) > 0), más <strong>{{ $customer->creditProfile->grace_days }} días de gracia</strong>@endif.</p>
                </header>

                @can('configure-customer-credit')
                    <div class="grid gap-6 p-5 lg:grid-cols-2 sm:p-7">
                        <form method="POST" action="{{ route('customers.credit-profile.update', $customer) }}" class="space-y-4">
                            @csrf @method('PUT')
                            <div><h3 class="font-black text-ink-950">Configurar línea de crédito</h3><p class="text-sm text-ink-600">Todo cambio queda registrado con usuario, fecha y motivo.</p></div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><label class="form-label" for="credit-status">Estado *</label><select class="form-input" id="credit-status" name="status" required>@foreach($creditStatuses as $status)<option value="{{ $status->value }}" @selected(old('status', $customer->creditProfile?->status->value ?? 'disabled') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                                <div><label class="form-label" for="credit-limit">Límite *</label><input class="form-input" id="credit-limit" name="credit_limit" type="number" min="0" step="0.01" value="{{ old('credit_limit', $customer->creditProfile?->credit_limit ?? '0') }}" required></div>
                                <div><label class="form-label" for="credit-term">Plazo en días *</label><input class="form-input" id="credit-term" name="term_days" type="number" min="0" max="365" step="1" value="{{ old('term_days', $customer->creditProfile?->term_days ?? 30) }}" required></div>
                                <div><label class="form-label" for="credit-grace">Días de gracia *</label><input class="form-input" id="credit-grace" name="grace_days" type="number" min="0" max="365" step="1" value="{{ old('grace_days', $customer->creditProfile?->grace_days ?? 0) }}" required></div>
                            </div>
                            <div><label class="form-label" for="credit-notes">Condiciones internas</label><textarea class="form-input min-h-20" id="credit-notes" name="notes" maxlength="2000">{{ old('notes', $customer->creditProfile?->notes) }}</textarea></div>
                            <div><label class="form-label" for="credit-reason">Motivo del cambio *</label><textarea class="form-input min-h-20" id="credit-reason" name="reason" maxlength="2000" required placeholder="Ejemplo: límite aprobado por gerencia">{{ old('reason') }}</textarea></div>
                            <div class="flex justify-end"><button class="btn-primary" type="submit">Guardar política</button></div>
                        </form>

                        <form method="POST" action="{{ route('customers.opening-balances.store', $customer) }}" class="space-y-4 rounded-2xl bg-stone-50 p-5">
                            @csrf
                            <div><h3 class="font-black text-ink-950">Registrar saldo inicial</h3><p class="text-sm text-ink-600">Úsalo una sola vez para importar deuda previa a Proximus. No se elimina del historial.</p></div>
                            <div><label class="form-label" for="opening-amount">Monto *</label><input class="form-input" id="opening-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required></div>
                            <div><label class="form-label" for="opening-document">Documento anterior</label><input class="form-input" id="opening-document" name="document_number" maxlength="80" value="{{ old('document_number') }}" placeholder="Se genera automáticamente si lo omites"></div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><label class="form-label" for="opening-issued">Fecha de emisión *</label><input class="form-input" id="opening-issued" name="issued_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('issued_at', now()->toDateString()) }}" required></div>
                                <div><label class="form-label" for="opening-due">Fecha de vencimiento *</label><input class="form-input" id="opening-due" name="due_date" type="date" value="{{ old('due_date', now()->toDateString()) }}" required></div>
                            </div>
                            <div><label class="form-label" for="opening-notes">Explicación</label><textarea class="form-input min-h-20" id="opening-notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></div>
                            <div class="flex justify-end"><button class="btn-secondary" type="submit">Registrar saldo inicial</button></div>
                        </form>
                    </div>
                @endcan

                @if($customer->creditProfileHistory->isNotEmpty() || $customer->creditAuthorizations->isNotEmpty())
                    <div class="grid gap-6 border-t border-stone-100 p-5 lg:grid-cols-2 sm:p-7">
                        <div>
                            <h3 class="font-black text-ink-950">Cambios de política</h3>
                            <div class="mt-3 space-y-3">
                                @forelse($customer->creditProfileHistory->take(8) as $change)
                                    <div class="rounded-xl bg-stone-50 p-3 text-sm"><p class="font-bold text-ink-950">{{ $change->to_status->label() }} · {{ $currency }} {{ number_format((float) $change->new_credit_limit, 2) }}</p><p class="text-ink-600">{{ $change->created_at->format('d/m/Y H:i') }} · {{ $change->changedBy->name }}</p><p class="mt-1 text-ink-700">{{ $change->reason }}</p></div>
                                @empty
                                    <p class="mt-2 text-sm text-ink-600">Aún no hay cambios registrados.</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <h3 class="font-black text-ink-950">Excepciones autorizadas</h3>
                            <div class="mt-3 space-y-3">
                                @forelse($customer->creditAuthorizations->take(8) as $authorization)
                                    <div class="rounded-xl bg-amber-50 p-3 text-sm"><p class="font-bold text-amber-950">{{ $authorization->order?->order_number ?: 'Crédito en entrega' }} · excedió {{ $currency }} {{ number_format((float) $authorization->exceeded_by, 2) }}</p><p class="text-ink-600">{{ $authorization->authorized_at->format('d/m/Y H:i') }} · {{ $authorization->authorizedBy->name }}</p><p class="mt-1 text-ink-700">{{ $authorization->reason }}</p></div>
                                @empty
                                    <p class="mt-2 text-sm text-ink-600">No se han autorizado excepciones al límite.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="card overflow-hidden" id="account">
                <header class="border-b border-stone-100 p-5 sm:p-7">
                    <p class="text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase">Cuenta corriente</p>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
                        <div><h2 class="text-xl font-black text-ink-950">Compras, crédito y abonos</h2><p class="text-sm text-ink-600">Cada movimiento conserva el pedido, recibo, método, usuario y fecha.</p></div>
                        <div class="rounded-2xl {{ bccomp($accountBalance, '0', 4) > 0 ? 'bg-amber-100 text-amber-950' : 'bg-emerald-100 text-emerald-950' }} px-5 py-3 text-right"><p class="text-xs font-bold uppercase">Saldo pendiente</p><p class="text-2xl font-black">{{ $currency }} {{ number_format((float) $accountBalance, 2) }}</p></div>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Compras registradas</p><p class="mt-1 text-lg font-black">{{ $currency }} {{ number_format((float) $purchasesTotal, 2) }}</p></div>
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Pagos y abonos</p><p class="mt-1 text-lg font-black text-emerald-700">{{ $currency }} {{ number_format((float) $paymentsTotal, 2) }}</p></div>
                        <div class="rounded-xl bg-stone-50 p-4"><p class="text-xs font-bold text-ink-600 uppercase">Deudas abiertas</p><p class="mt-1 text-lg font-black">{{ $openReceivables->count() }}</p></div>
                    </div>
                </header>

                @can('record-customer-payments')
                @if($openReceivables->isNotEmpty())
                    <div class="border-b border-stone-100 p-5 sm:p-7">
                        <div class="mb-4"><h3 class="font-black text-ink-950">Registrar abono</h3><p class="text-sm text-ink-600">Indica cuánto recibiste y distribúyelo entre una o varias deudas. El sistema no permite aplicar más del saldo vigente.</p></div>
                        <form method="POST" action="{{ route('customers.payments.store', $customer) }}" class="space-y-5" data-customer-payment-form data-currency="{{ $currency }}"><x-location-fields />
                            @csrf
                            <input name="amount" type="hidden" value="0" data-customer-payment-total-input>
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-3"><div><h4 class="font-bold text-ink-950">Formas de pago</h4><p class="text-sm text-ink-600">Puedes combinar efectivo, transferencia, tarjeta o cheque en el mismo recibo.</p></div><button class="btn-secondary min-h-9 px-3 py-1.5" type="button" data-add-customer-payment>Agregar método</button></div>
                                <div class="space-y-3" data-customer-payment-rows>
                                    <div class="grid gap-3 rounded-xl border border-stone-200 p-3 md:grid-cols-[13rem_10rem_minmax(0,1fr)_auto] md:items-end" data-customer-payment-row>
                                        <div><label class="form-label">Método *</label><select class="form-input" name="payment_lines[0][method]" required data-customer-payment-method>@foreach($paymentMethods as $method)<option value="{{ $method->value }}" data-requires-reference="{{ $method->requiresReference() ? 'true' : 'false' }}">{{ $method->label() }}</option>@endforeach</select></div>
                                        <div><label class="form-label">Monto *</label><input class="form-input" name="payment_lines[0][amount]" type="number" min="0.01" step="0.01" inputmode="decimal" required placeholder="0.00" data-customer-payment-line-amount></div>
                                        <div><label class="form-label">Referencia <span data-customer-reference-label>(opcional)</span></label><input class="form-input" name="payment_lines[0][reference]" maxlength="120" placeholder="Número de transferencia, cheque o autorización" data-customer-payment-reference></div>
                                        <button class="btn-secondary min-h-11 px-3" type="button" disabled aria-label="No se puede quitar el único método">Quitar</button>
                                    </div>
                                </div>
                                <template data-customer-payment-row-template>
                                    <div class="grid gap-3 rounded-xl border border-stone-200 p-3 md:grid-cols-[13rem_10rem_minmax(0,1fr)_auto] md:items-end" data-customer-payment-row>
                                        <div><label class="form-label">Método *</label><select class="form-input" name="payment_lines[__INDEX__][method]" required data-customer-payment-method>@foreach($paymentMethods as $method)<option value="{{ $method->value }}" data-requires-reference="{{ $method->requiresReference() ? 'true' : 'false' }}">{{ $method->label() }}</option>@endforeach</select></div>
                                        <div><label class="form-label">Monto *</label><input class="form-input" name="payment_lines[__INDEX__][amount]" type="number" min="0.01" step="0.01" inputmode="decimal" required placeholder="0.00" data-customer-payment-line-amount></div>
                                        <div><label class="form-label">Referencia <span data-customer-reference-label>(opcional)</span></label><input class="form-input" name="payment_lines[__INDEX__][reference]" maxlength="120" placeholder="Número de transferencia, cheque o autorización" data-customer-payment-reference></div>
                                        <button class="btn-secondary min-h-11 px-3" type="button" data-remove-customer-payment>Quitar</button>
                                    </div>
                                </template>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-stone-200">
                                <table class="w-full min-w-[720px]">
                                    <thead class="bg-stone-50"><tr><th class="table-heading">Pedido</th><th class="table-heading">Fecha</th><th class="table-heading text-right">Crédito original</th><th class="table-heading text-right">Saldo</th><th class="table-heading">Aplicar ahora</th></tr></thead>
                                    <tbody>
                                        @foreach($openReceivables->sortBy('opened_at')->values() as $index => $receivable)
                                            <tr data-receivable-row>
                                                <td class="table-cell"><p class="font-bold text-ink-950">{{ $receivable->order?->order_number ?: $receivable->document_number }}</p><p class="text-xs text-ink-600">{{ $receivable->source->label() }}@if($receivable->due_date) · vence {{ $receivable->due_date->format('d/m/Y') }}@endif</p><input type="hidden" name="allocations[{{ $index }}][receivable_id]" value="{{ $receivable->id }}"></td>
                                                <td class="table-cell">{{ $receivable->opened_at->format('d/m/Y') }}</td>
                                                <td class="table-cell text-right">{{ $currency }} {{ number_format((float) $receivable->original_amount, 2) }}</td>
                                                <td class="table-cell text-right font-black text-amber-800">{{ $currency }} {{ number_format((float) $receivable->outstanding_amount, 2) }}</td>
                                                <td class="table-cell"><input class="form-input min-h-9 w-36 py-1.5" aria-label="Abono al documento {{ $receivable->order?->order_number ?: $receivable->document_number }}" name="allocations[{{ $index }}][amount]" type="number" min="0" max="{{ $receivable->outstanding_amount }}" step="0.01" value="0" data-receivable-allocation data-outstanding="{{ $receivable->outstanding_amount }}"></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-stone-50 p-3"><p class="text-xs font-bold text-ink-600 uppercase">Recibido</p><p class="mt-1 text-lg font-black" data-customer-received-total>{{ $currency }} 0.00</p></div>
                                <div class="rounded-xl bg-stone-50 p-3"><p class="text-xs font-bold text-ink-600 uppercase">Aplicado</p><p class="mt-1 text-lg font-black text-emerald-700" data-customer-applied-total>{{ $currency }} 0.00</p></div>
                                <div class="rounded-xl bg-amber-50 p-3"><p class="text-xs font-bold text-amber-800 uppercase">Falta aplicar</p><p class="mt-1 text-lg font-black text-amber-900" data-customer-unallocated-total>{{ $currency }} 0.00</p></div>
                            </div>
                            <p class="hidden rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700" data-customer-payment-error>El total aplicado debe coincidir exactamente con el monto recibido.</p>
                            <div><label class="form-label" for="customer-payment-notes">Observaciones</label><textarea class="form-input min-h-20" id="customer-payment-notes" name="notes" maxlength="2000" placeholder="Detalle opcional del abono"></textarea></div>
                            <div class="flex flex-wrap justify-end gap-3"><button class="btn-secondary" type="button" data-auto-allocate>Distribuir desde la deuda más antigua</button><button class="btn-primary" type="submit" data-customer-payment-submit disabled>Registrar abono</button></div>
                        </form>
                    </div>
                @endif
                @endcan

                <div class="p-5 sm:p-7">
                    <div class="mb-4"><h3 class="font-black text-ink-950">Historial financiero</h3><p class="text-sm text-ink-600">Se muestran los 100 movimientos más recientes.</p></div>
                    @if($customer->accountEntries->isEmpty())
                        <x-empty-state title="Sin movimientos" description="Las compras entregadas y sus pagos aparecerán aquí." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1040px]">
                                <thead class="bg-stone-50"><tr><th class="table-heading">Fecha</th><th class="table-heading">Movimiento</th><th class="table-heading">Pedido / recibo</th><th class="table-heading">Métodos</th><th class="table-heading text-right">Cargo</th><th class="table-heading text-right">Abono</th><th class="table-heading text-right">Acción</th></tr></thead>
                                <tbody>
                                    @foreach($customer->accountEntries as $entry)
                                        <tr class="{{ $entry->status === \App\CustomerAccountEntryStatus::Reversed ? 'opacity-60' : '' }}">
                                            <td class="table-cell"><p class="font-semibold">{{ $entry->occurred_at->format('d/m/Y') }}</p><p class="text-xs text-ink-600">{{ $entry->occurred_at->format('H:i') }} · {{ $entry->createdBy->name }}</p></td>
                                            <td class="table-cell"><p class="font-bold text-ink-950">{{ $entry->type->label() }}</p>@if($entry->status === \App\CustomerAccountEntryStatus::Reversed)<span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">Revertido</span>@endif @if($entry->notes)<p class="mt-1 max-w-xs text-xs text-ink-600">{{ $entry->notes }}</p>@endif</td>
                                            <td class="table-cell"><p class="font-semibold">{{ $entry->order?->order_number ?: '—' }}</p><p class="text-xs text-ink-600">{{ $entry->receipt_number ?: 'Sin recibo' }}</p>@if($entry->reversalOf)<p class="text-xs text-red-700">Revierte {{ $entry->reversalOf->receipt_number }}</p>@endif</td>
                                            <td class="table-cell">
                                                @if($entry->paymentLines->isNotEmpty())
                                                    @foreach($entry->paymentLines as $line)<p>{{ $line->method->label() }} · {{ $currency }} {{ number_format((float) $line->amount, 2) }}@if($line->reference) <span class="text-xs text-ink-600">(Ref. {{ $line->reference }})</span>@endif</p>@endforeach
                                                @else
                                                    <p>{{ $entry->method?->label() ?: ($entry->type === \App\CustomerAccountEntryType::Sale ? 'Crédito de venta' : '—') }}</p>
                                                    @if($entry->reference)<p class="text-xs text-ink-600">Ref. {{ $entry->reference }}</p>@endif
                                                @endif
                                            </td>
                                            <td class="table-cell text-right font-black {{ bccomp($entry->debit_amount, '0', 4) > 0 ? 'text-amber-800' : 'text-stone-400' }}">{{ bccomp($entry->debit_amount, '0', 4) > 0 ? $currency.' '.number_format((float) $entry->debit_amount, 2) : '—' }}</td>
                                            <td class="table-cell text-right font-black {{ bccomp($entry->credit_amount, '0', 4) > 0 ? 'text-emerald-700' : 'text-stone-400' }}">{{ bccomp($entry->credit_amount, '0', 4) > 0 ? $currency.' '.number_format((float) $entry->credit_amount, 2) : '—' }}</td>
                                            <td class="table-cell text-right">
                                                @if($entry->type === \App\CustomerAccountEntryType::CustomerPayment)
                                                    <a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('customers.payments.receipt.show', [$customer, $entry]) }}">Comprobante</a>
                                                @endif
                                                @can('reverse-customer-payments')
                                                    @if($entry->type === \App\CustomerAccountEntryType::CustomerPayment && $entry->status === \App\CustomerAccountEntryStatus::Active)
                                                        <details class="relative inline-block text-left"><summary class="btn-secondary min-h-9 cursor-pointer list-none px-3 py-1.5">Revertir</summary><form method="POST" action="{{ route('customers.payments.reverse', [$customer, $entry]) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border border-stone-200 bg-white p-4 text-left shadow-xl">@csrf<label class="form-label">Motivo obligatorio</label><textarea class="form-input min-h-20" name="reason" maxlength="2000" required></textarea><button class="btn-danger w-full" type="submit">Confirmar reversión</button></form></details>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            @can('manage-orders')
                <section class="card p-5 sm:p-7">
                    <h2 class="font-bold text-ink-950">Visita sin pedido</h2>
                    <p class="text-sm text-ink-600">Deja constancia de la visita cuando el cliente no compra.</p>
                    <form method="POST" action="{{ route('customers.visits.store', $customer) }}" class="mt-4 grid gap-4 sm:grid-cols-[14rem_minmax(0,1fr)_auto]">
                        @csrf
                        <x-location-fields />
                        <div><label class="form-label" for="visit-reason">Motivo *</label><select class="form-input" id="visit-reason" name="reason" required><option value="">Selecciona…</option>@foreach(\App\CustomerVisitReason::cases() as $visitReason)<option value="{{ $visitReason->value }}" @selected(old('reason') === $visitReason->value)>{{ $visitReason->label() }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="visit-notes">Observaciones</label><input class="form-input" id="visit-notes" name="notes" maxlength="1000" value="{{ old('notes') }}"></div>
                        <div class="flex items-end"><button class="btn-secondary w-full" type="submit">Registrar visita</button></div>
                    </form>
                </section>
            @endcan

            <section class="card overflow-hidden">
                <div class="border-b border-stone-100 px-5 py-4">
                    <h2 class="font-bold text-ink-950">Visitas programadas</h2>
                    <p class="text-sm text-ink-600">Un mismo cliente puede aparecer en varias rutas y días.</p>
                </div>
                @if ($customer->routeStops->isEmpty())
                    <div class="p-6"><x-empty-state title="Sin rutas asignadas" description="Abre una ruta para programar la visita de este cliente." /></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px]">
                            <thead class="bg-stone-50"><tr><th class="table-heading">Ruta</th><th class="table-heading">Día</th><th class="table-heading">Orden</th><th class="table-heading">Estado</th><th class="table-heading text-right">Acción</th></tr></thead>
                            <tbody>
                                @foreach ($customer->routeStops as $stop)
                                    <tr>
                                        <td class="table-cell"><p class="font-semibold text-ink-950">{{ $stop->salesRoute->name }}</p><p class="text-xs text-ink-600">{{ $stop->salesRoute->code }}</p></td>
                                        <td class="table-cell">{{ $stop->visit_day->label() }}</td>
                                        <td class="table-cell">#{{ $stop->visit_order }}</td>
                                        <td class="table-cell"><x-status-badge :active="$stop->is_active" /></td>
                                        <td class="table-cell text-right"><a class="btn-secondary min-h-9 px-3 py-1.5" href="{{ route('routes.show', $stop->salesRoute) }}">Ver ruta</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <div class="card p-5">
                <p class="text-sm font-semibold text-leaf-700">Resumen</p>
                <p class="mt-2 text-3xl font-black text-ink-950">{{ $customer->routeStops->count() }}</p>
                <p class="text-sm text-ink-600">visitas semanales configuradas</p>
            </div>
            <div class="card p-5">
                <h2 class="font-bold text-ink-950">Eliminar cliente</h2>
                <p class="mt-2 text-sm leading-6 text-ink-600">Solo se puede eliminar cuando ya no tiene visitas asignadas. Para conservar el historial, también puedes desactivarlo.</p>
                <form class="mt-4" method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="¿Eliminar este cliente? Esta acción no se puede deshacer.">
                    @csrf @method('DELETE')
                    <button class="btn-danger w-full" type="submit">Eliminar cliente</button>
                </form>
            </div>
        </aside>
    </div>
@endsection
