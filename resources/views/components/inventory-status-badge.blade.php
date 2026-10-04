@props(['status'])

@php
    $value = $status->value;
    $classes = match ($value) {
        'posted' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp

<span {{ $attributes->class("inline-flex rounded-full px-2.5 py-1 text-xs font-bold {$classes}") }}>{{ $status->label() }}</span>
