@props([
    'name',
    'label',
    'endpoint',
    'type',
    'inputId' => null,
    'required' => false,
    'selectedId' => null,
    'selectedName' => null,
    'selectedMeta' => null,
    'selectedImageUrl' => null,
    'mode' => null,
    'warehouseId' => null,
    'warehouseSelector' => null,
    'placeholder' => null,
    'help' => null,
    'scanner' => false,
])

@php($resolvedId = $inputId ?: str_replace(['[', ']'], ['-', ''], $name))

<div
    class="relative"
    data-remote-picker
    data-picker-type="{{ $type }}"
    data-picker-endpoint="{{ $endpoint }}"
    @if($mode) data-picker-mode="{{ $mode }}" @endif
    @if($warehouseId) data-picker-warehouse-id="{{ $warehouseId }}" @endif
    @if($warehouseSelector) data-picker-warehouse-selector="{{ $warehouseSelector }}" @endif
>
    <label class="form-label" for="{{ $resolvedId }}-search">{{ $label }} @if($required)*@endif</label>
    <input
        type="hidden"
        id="{{ $resolvedId }}"
        name="{{ $name }}"
        value="{{ old($name, $selectedId) }}"
        @if($required) required @endif
        data-remote-picker-value
        @if($type === 'product') data-product-presentation-value @endif
    >
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input
                class="form-input pr-10 pl-10"
                id="{{ $resolvedId }}-search"
                type="search"
                autocomplete="off"
                placeholder="{{ $placeholder ?: ($type === 'product' ? 'Nombre, SKU, código de barras…' : 'Nombre, código, teléfono, dirección…') }}"
                data-remote-picker-search
                aria-autocomplete="list"
                aria-expanded="false"
            >
            <span class="pointer-events-none absolute top-1/2 right-3 hidden size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-stone-300 border-t-leaf-700" data-remote-picker-loading aria-hidden="true"></span>
        </div>
        @if($scanner)
            <button class="btn-secondary shrink-0 px-3" type="button" data-remote-picker-scan title="Escanear con cámara o lector" aria-label="Escanear código de barras">
                <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5v4M3 5h4M21 5h-4M21 5v4M3 19v-4M3 19h4M21 19h-4M21 19v-4M7 8v8M10 8v8M14 8v8M17 8v8"/></svg>
                <span class="hidden sm:inline">Escanear</span>
            </button>
        @endif
    </div>

    <div class="absolute z-40 mt-2 hidden max-h-[32rem] w-full overflow-y-auto rounded-2xl border border-stone-200 bg-white p-2 shadow-2xl" data-remote-picker-results role="listbox"></div>

    <div class="mt-3 {{ old($name, $selectedId) ? '' : 'hidden' }}" data-remote-picker-selected>
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <div class="{{ $selectedImageUrl ? '' : 'hidden' }} size-14 shrink-0 overflow-hidden rounded-lg bg-white" data-picker-selected-image-wrap>
                <img class="h-full w-full object-contain" src="{{ $selectedImageUrl }}" alt="" data-picker-selected-image>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-emerald-950" data-picker-selected-name>{{ $selectedName }}</p>
                <p class="truncate text-xs text-emerald-800" data-picker-selected-meta>{{ $selectedMeta }}</p>
            </div>
            <button class="rounded-lg p-2 text-emerald-800 hover:bg-white" type="button" data-remote-picker-clear aria-label="Cambiar selección">Cambiar</button>
        </div>
    </div>

    @if($help)
        <p class="form-help">{{ $help }}</p>
    @elseif($scanner)
        <p class="form-help">También admite lector USB/Bluetooth: enfoca el campo y escanea el código.</p>
    @endif
    <p class="mt-1 hidden text-xs text-red-700" data-remote-picker-error aria-live="polite"></p>
</div>
