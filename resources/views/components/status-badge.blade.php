@props(['active' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.($active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-600')]) }}>
    <span class="mr-1.5 size-1.5 rounded-full {{ $active ? 'bg-emerald-500' : 'bg-stone-400' }}"></span>
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
