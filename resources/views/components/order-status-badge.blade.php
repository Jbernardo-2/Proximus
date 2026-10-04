@props(['status'])

<span @class([
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold',
    'bg-amber-100 text-amber-800' => $status === \App\OrderStatus::Draft,
    'bg-emerald-100 text-emerald-800' => $status === \App\OrderStatus::Confirmed,
    'bg-violet-100 text-violet-800' => $status === \App\OrderStatus::Assigned,
    'bg-sky-100 text-sky-800' => $status === \App\OrderStatus::Loaded,
    'bg-blue-100 text-blue-800' => $status === \App\OrderStatus::InTransit,
    'bg-green-100 text-green-800' => $status === \App\OrderStatus::Delivered,
    'bg-orange-100 text-orange-800' => $status === \App\OrderStatus::PartiallyDelivered,
    'bg-stone-200 text-stone-800' => $status === \App\OrderStatus::NotDelivered,
    'bg-red-100 text-red-800' => $status === \App\OrderStatus::Cancelled,
])>
    {{ $status->label() }}
</span>
