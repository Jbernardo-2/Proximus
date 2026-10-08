@props(['status'])

@php
    $classes = match ($status) {
        \App\DeliveryOrderStatus::Pending => 'bg-stone-100 text-stone-700',
        \App\DeliveryOrderStatus::Prepared => 'bg-amber-100 text-amber-800',
        \App\DeliveryOrderStatus::Loaded => 'bg-sky-100 text-sky-800',
        \App\DeliveryOrderStatus::Delivered => 'bg-emerald-100 text-emerald-800',
        \App\DeliveryOrderStatus::PartiallyDelivered => 'bg-amber-100 text-amber-800',
        \App\DeliveryOrderStatus::NotDelivered => 'bg-red-100 text-red-800',
        \App\DeliveryOrderStatus::Cancelled => 'bg-stone-200 text-stone-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {$classes}"]) }}>
    {{ $status->label() }}
</span>
