@extends('layouts.guest')

@section('title', 'Descargar la app')

@section('content')
    <main class="min-h-dvh bg-sand-50 px-5 py-10 sm:px-10">
        <div class="mx-auto w-full max-w-md">
            <img src="{{ asset('images/proximus-logo-horizontal.svg') }}" alt="Proximus" class="h-auto w-48" width="752" height="180">

            <h1 class="mt-10 text-3xl font-semibold tracking-[-0.03em] text-ink-950">App de Proximus para Android</h1>
            <p class="mt-3 text-sm leading-6 text-ink-600">Para preventistas, repartidores, bodega y supervisión. Necesitas un usuario creado por el administrador.</p>

            @if ($available)
                <a href="{{ route('app-download.file') }}" class="btn-primary mt-8 w-full py-3.5 text-[0.95rem] shadow-lg shadow-leaf-700/15">
                    Descargar la app
                </a>
                <p class="mt-3 text-center text-xs text-ink-600">
                    Versión {{ $version }} · {{ $megabytes }} MB · actualizada el {{ $updatedAt->translatedFormat('j \d\e F \d\e Y') }}
                </p>

                <section class="mt-10 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-ink-950">Cómo instalarla</h2>
                    <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm leading-6 text-ink-600">
                        <li>Abre esta página en <strong>Chrome</strong> desde el teléfono y toca <strong>Descargar la app</strong>.</li>
                        <li>Cuando termine, toca el archivo. Si Android pide permiso para <strong>instalar apps de esta fuente</strong>, actívalo para Chrome y vuelve.</li>
                        <li>Si aparece un aviso de <strong>Play Protect</strong>, elige <strong>Más detalles → Instalar de todos modos</strong>. La app es de tu empresa y no está en la Play Store.</li>
                        <li>Abre Proximus e inicia sesión con tu correo y contraseña.</li>
                    </ol>
                </section>

                <section class="mt-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-ink-950">Para actualizarla</h2>
                    <p class="mt-3 text-sm leading-6 text-ink-600">Sincroniza antes, descarga la versión nueva desde aquí e instálala encima. <strong>No desinstales la app</strong>: lo que no se haya sincronizado se perdería.</p>
                </section>
            @else
                <p class="mt-8 rounded-2xl border border-stone-200 bg-white p-5 text-sm text-ink-600 shadow-sm">La app todavía no está disponible para descargar.</p>
            @endif
        </div>
    </main>
@endsection
