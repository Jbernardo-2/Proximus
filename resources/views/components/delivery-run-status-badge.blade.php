@props(['status'])

@php
    $classes = match ($status) {
        \App\DeliveryRunStatus::Draft => 'bg-stone-100 text-stone-700',
        \App\DeliveryRunStatus::Preparing => 'bg-amber-100 text-amber-800',
        \App\DeliveryRunStatus::Loaded => 'bg-sky-100 text-sky-800',
        \App\DeliveryRunStatus::InTransit => 'bg-indigo-100 text-indigo-800',
        \App\DeliveryRunStatus::AwaitingSettlement => 'bg-violet-100 text-violet-800',
        \App\DeliveryRunStatus::Settled => 'bg-emerald-100 text-emerald-800',
        \App\DeliveryRunStatus::Cancelled => 'bg-red-100 text-red-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {$classes}"]) }}>
    {{ $status->label() }}
</span>
