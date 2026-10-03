@props(['title', 'description'])

<div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center">
    <div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-mint-100 text-xl text-leaf-700">＋</div>
    <h3 class="font-bold text-ink-950">{{ $title }}</h3>
    <p class="mx-auto mt-1 max-w-md text-sm text-ink-600">{{ $description }}</p>
    @if (isset($action))
        <div class="mt-5">{{ $action }}</div>
    @endif
</div>
