@props(['summary'])

@php
    $severityClasses = [
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'important' => 'bg-red-500',
        'info' => 'bg-sky-500',
    ];
@endphp

<div
    class="relative"
    data-notification-center
    data-feed-url="{{ route('notifications.feed') }}"
    data-read-url="{{ route('notifications.read', '__ID__') }}"
    data-read-all-url="{{ route('notifications.read-all') }}"
>
    <button type="button" class="relative grid size-10 place-items-center rounded-xl border border-stone-200 bg-white text-lg text-ink-700 transition hover:border-leaf-300 hover:bg-mint-50" data-notification-toggle aria-expanded="false" aria-label="Notificaciones: {{ $summary['unread_count'] }} sin leer">
        <span aria-hidden="true">◆</span>
        <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[10px] font-black leading-4 text-white {{ $summary['unread_count'] === 0 ? 'hidden' : '' }}" data-notification-badge>{{ $summary['unread_count'] > 99 ? '99+' : $summary['unread_count'] }}</span>
    </button>

    <div class="absolute right-0 z-50 mt-3 hidden w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl" data-notification-panel>
        <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-3">
            <div><p class="font-black text-ink-950">Novedades</p><p class="text-xs text-ink-600">Trabajo y cambios importantes</p></div>
            <button type="button" class="text-xs font-bold text-leaf-700 hover:underline" data-notification-read-all>Marcar leídas</button>
        </div>
        <div class="max-h-96 divide-y divide-stone-100 overflow-y-auto" data-notification-list>
            @forelse($summary['notifications'] as $notification)
                @php
                    $severity = (string) ($notification->data['severity'] ?? 'info');
                    $url = (string) ($notification->data['url'] ?? route('dashboard'));
                @endphp
                <a
                    href="{{ $url }}"
                    class="flex gap-3 px-4 py-3 transition hover:bg-stone-50 {{ $notification->read_at ? 'opacity-65' : 'bg-mint-50/60' }}"
                    data-notification-id="{{ $notification->id }}"
                    data-notification-read="{{ $notification->read_at ? 'true' : 'false' }}"
                    data-read-url="{{ route('notifications.read', $notification) }}"
                >
                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $severityClasses[$severity] ?? $severityClasses['info'] }}"></span>
                    <span class="min-w-0"><span class="block truncate text-sm font-bold text-ink-950">{{ $notification->data['title'] ?? 'Notificación' }}</span><span class="mt-0.5 block text-xs text-ink-600">{{ $notification->data['message'] ?? '' }}</span><span class="mt-1 block text-[10px] font-semibold text-ink-500">{{ $notification->created_at?->diffForHumans() }}</span></span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-600">No tienes novedades todavía.</p>
            @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" class="block border-t border-stone-100 px-4 py-3 text-center text-sm font-bold text-leaf-700 hover:bg-mint-50">Ver todo el historial</a>
    </div>

    <a href="{{ route('notifications.index') }}" class="fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-2xl border border-leaf-200 bg-white p-4 shadow-2xl" data-notification-toast>
        <p class="text-xs font-black tracking-wide text-leaf-700 uppercase">Nueva notificación</p>
        <p class="mt-1 font-black text-ink-950" data-toast-title></p>
        <p class="mt-1 text-sm text-ink-600" data-toast-message></p>
    </a>
</div>
