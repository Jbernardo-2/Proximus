@props(['status'])

<span @class([
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold',
    'bg-amber-100 text-amber-800' => $status === \App\OrderStatus::Draft,
    'bg-emerald-100 text-emerald-800' => $status === \App\OrderStatus::Confirmed,
    'bg-red-100 text-red-800' => $status === \App\OrderStatus::Cancelled,
])>
    {{ $status->label() }}
</span>
