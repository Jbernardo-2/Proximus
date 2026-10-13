@extends('layouts.app')

@section('title', 'Notificaciones')
@section('page-title', 'Centro de notificaciones')
@section('page-subtitle', 'Historial de novedades y trabajo que requiere tu atención.')

@section('header-actions')
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn-secondary" type="submit">Marcar todo como leído</button>
    </form>
@endsection

@section('content')
    <section class="card overflow-hidden">
        <div class="divide-y divide-stone-100">
            @forelse($notifications as $notification)
                @php
                    $severityClasses = [
                        'success' => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        'important' => 'bg-red-500',
                        'info' => 'bg-sky-500',
                    ];
                    $severity = (string) ($notification->data['severity'] ?? 'info');
                @endphp
                <article class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between {{ $notification->read_at ? '' : 'bg-mint-50/50' }}">
                    <div class="flex min-w-0 gap-3">
                        <span class="mt-1.5 size-3 shrink-0 rounded-full {{ $severityClasses[$severity] ?? $severityClasses['info'] }}"></span>
                        <div><div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-ink-950">{{ $notification->data['title'] ?? 'Notificación' }}</h2>@if(!$notification->read_at)<span class="rounded-full bg-leaf-700 px-2 py-0.5 text-[10px] font-black text-white uppercase">Nueva</span>@endif</div><p class="mt-1 text-sm text-ink-600">{{ $notification->data['message'] ?? '' }}</p><p class="mt-1 text-xs text-ink-500">{{ $notification->created_at?->format('d/m/Y H:i') }} · {{ $notification->created_at?->diffForHumans() }}</p></div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if(!$notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button type="submit" class="btn-secondary">Marcar leída</button></form>
                        @endif
                        <a class="btn-primary" href="{{ $notification->data['url'] ?? route('dashboard') }}">Abrir</a>
                    </div>
                </article>
            @empty
                <div class="p-8"><x-empty-state title="Sin notificaciones" description="Aquí aparecerán los pedidos, jornadas y novedades que correspondan a tu perfil." /></div>
            @endforelse
        </div>
        @if($notifications->hasPages())
            <div class="border-t border-stone-100 px-5 py-4">{{ $notifications->links() }}</div>
        @endif
    </section>
@endsection
