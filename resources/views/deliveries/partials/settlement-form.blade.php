<div class="space-y-5 p-5">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold tracking-[0.14em] text-amber-800 uppercase">Resultado guardado</p>
                <h4 class="mt-1 text-lg font-black text-amber-950">Falta registrar el pago</h4>
                <p class="text-sm text-amber-900">La parada seguirá activa hasta distribuir todo el total entre pagos y/o crédito.</p>
            </div>
            <p class="text-2xl font-black text-amber-950">{{ $currency }} {{ number_format((float) $runOrder->delivered_total, 2) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead class="bg-stone-50"><tr><th class="table-heading">Producto</th><th class="table-heading text-right">Cargado</th><th class="table-heading text-right">Entregado</th><th class="table-heading text-right">Devuelto</th><th class="table-heading text-right">Dañado</th><th class="table-heading text-right">Faltante</th></tr></thead>
            <tbody>
                @foreach($runOrder->items as $item)
                    <tr><td class="table-cell"><p class="font-semibold">{{ $item->product_name }}</p><p class="text-xs text-ink-600">{{ $item->presentation_name }}</p></td><td class="table-cell text-right">{{ $quantity($item->loaded_quantity) }}</td><td class="table-cell text-right font-bold text-emerald-700">{{ $quantity($item->delivered_quantity) }}</td><td class="table-cell text-right">{{ $quantity($item->returned_quantity) }}</td><td class="table-cell text-right text-red-700">{{ $quantity($item->damaged_quantity) }}</td><td class="table-cell text-right text-red-700">{{ $quantity($item->missing_quantity) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('delivery-runs.orders.payments.store', [$deliveryRun, $runOrder]) }}" class="space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4" data-combined-payment-form data-balance="{{ $runOrder->delivered_total }}" data-currency="{{ $currency }}">
        @csrf
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h4 class="font-black text-emerald-950">Distribución del pago</h4><p class="text-sm text-emerald-800">El crédito se agrega como un método explícito y crea una deuda en la cuenta del cliente.</p></div>
            <button class="btn-secondary" type="button" data-add-payment>＋ Agregar método</button>
        </div>
        <div class="space-y-3" data-payment-rows>
            <div class="grid gap-3 rounded-xl border border-emerald-200 bg-white p-3 lg:grid-cols-[12rem_9rem_minmax(10rem,1fr)_auto]" data-payment-row>
                <div><label class="form-label" for="allocation-method-{{ $runOrder->id }}-0">Método *</label><select class="form-input" id="allocation-method-{{ $runOrder->id }}-0" name="allocations[0][method]" required data-payment-method>@foreach($settlementMethods as $method)<option value="{{ $method->value }}" data-requires-reference="{{ $method->requiresReference() ? 'true' : 'false' }}" data-is-credit="{{ $method->isCredit() ? 'true' : 'false' }}">{{ $method->label() }}</option>@endforeach</select></div>
                <div><label class="form-label" for="allocation-amount-{{ $runOrder->id }}-0">Monto *</label><input class="form-input" id="allocation-amount-{{ $runOrder->id }}-0" name="allocations[0][amount]" type="number" inputmode="decimal" min="0.01" max="{{ $runOrder->delivered_total }}" step="0.01" required value="{{ number_format((float) $runOrder->delivered_total, 2, '.', '') }}" data-payment-amount></div>
                <div><label class="form-label" for="allocation-reference-{{ $runOrder->id }}-0">Referencia <span data-reference-required-label>(opcional)</span></label><input class="form-input" id="allocation-reference-{{ $runOrder->id }}-0" name="allocations[0][reference]" maxlength="120" placeholder="Transferencia, cheque o autorización" data-payment-reference></div>
                <div class="flex items-end"><button class="btn-secondary hidden" type="button" data-remove-payment>Quitar</button></div>
            </div>
        </div>
        <template data-payment-row-template>
            <div class="grid gap-3 rounded-xl border border-emerald-200 bg-white p-3 lg:grid-cols-[12rem_9rem_minmax(10rem,1fr)_auto]" data-payment-row>
                <div><label class="form-label" for="allocation-method-{{ $runOrder->id }}-__INDEX__">Método *</label><select class="form-input" id="allocation-method-{{ $runOrder->id }}-__INDEX__" name="allocations[__INDEX__][method]" required data-payment-method>@foreach($settlementMethods as $method)<option value="{{ $method->value }}" data-requires-reference="{{ $method->requiresReference() ? 'true' : 'false' }}" data-is-credit="{{ $method->isCredit() ? 'true' : 'false' }}">{{ $method->label() }}</option>@endforeach</select></div>
                <div><label class="form-label" for="allocation-amount-{{ $runOrder->id }}-__INDEX__">Monto *</label><input class="form-input" id="allocation-amount-{{ $runOrder->id }}-__INDEX__" name="allocations[__INDEX__][amount]" type="number" inputmode="decimal" min="0.01" max="{{ $runOrder->delivered_total }}" step="0.01" required placeholder="0.00" data-payment-amount></div>
                <div><label class="form-label" for="allocation-reference-{{ $runOrder->id }}-__INDEX__">Referencia <span data-reference-required-label>(opcional)</span></label><input class="form-input" id="allocation-reference-{{ $runOrder->id }}-__INDEX__" name="allocations[__INDEX__][reference]" maxlength="120" placeholder="Transferencia, cheque o autorización" data-payment-reference></div>
                <div class="flex items-end"><button class="btn-secondary" type="button" data-remove-payment>Quitar</button></div>
            </div>
        </template>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold text-ink-600 uppercase">Total entregado</p><p class="mt-1 text-lg font-black">{{ $currency }} {{ number_format((float) $runOrder->delivered_total, 2) }}</p></div>
            <div class="rounded-xl bg-white p-3"><p class="text-xs font-bold text-ink-600 uppercase">Distribuido</p><p class="mt-1 text-lg font-black text-emerald-700" data-payment-total>{{ $currency }} {{ number_format((float) $runOrder->delivered_total, 2) }}</p></div>
            <div class="rounded-xl bg-amber-50 p-3"><p class="text-xs font-bold text-amber-800 uppercase">Falta distribuir</p><p class="mt-1 text-lg font-black text-amber-900" data-credit-total>{{ $currency }} 0.00</p></div>
        </div>
        <div class="hidden" data-credit-reason-wrap><label class="form-label" for="settlement-credit-{{ $runOrder->id }}">Acuerdo o motivo del crédito *</label><input class="form-input" id="settlement-credit-{{ $runOrder->id }}" name="credit_reason" maxlength="2000" value="{{ old('credit_reason') }}" placeholder="Ejemplo: cliente pagará el saldo el viernes" data-credit-reason></div>
        @if(auth()->user()->canOverrideCustomerCredit())
            <div><label class="form-label" for="settlement-credit-override-{{ $runOrder->id }}">Autorización excepcional <span class="font-normal">(solo si supera el disponible)</span></label><textarea class="form-input min-h-20" id="settlement-credit-override-{{ $runOrder->id }}" name="credit_override_reason" maxlength="2000" placeholder="Motivo obligatorio para autorizar un exceso de límite">{{ old('credit_override_reason') }}</textarea></div>
        @endif
        <p class="hidden rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700" data-payment-error></p>
        <div class="flex justify-end"><button class="btn-primary" type="submit" data-payment-submit>Registrar pago y completar entrega</button></div>
    </form>
</div>
