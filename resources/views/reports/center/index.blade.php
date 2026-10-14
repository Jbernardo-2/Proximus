@extends('layouts.app')

@section('title', 'Reportes')
@section('page-title', 'Centro de reportes')

@section('content')
    <div class="space-y-8">
        @foreach($groups as $group => $reports)
            <section>
                <h2 class="text-xs font-semibold tracking-[0.14em] text-ink-600 uppercase">{{ $group }}</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($reports as $report)
                        <a class="card block p-5 transition hover:border-leaf-600" href="{{ route('reports.show', $report->key()) }}">
                            <h3 class="font-bold text-ink-950">{{ $report->title() }}</h3>
                            <p class="mt-1 text-sm leading-6 text-ink-600">{{ $report->description() }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
