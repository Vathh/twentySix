@extends('layouts.app')

@section('title', $kind->plural().' — trening')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.training.index') }}" class="text-text-secondary text-sm hover:text-accent">← Trening</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">{{ $kind->plural() }}</h1>
        </div>
        @if($kind !== \App\Domain\Training\TrainingKind::Exercise)
            <a href="{{ route('admin.training.create', $kind->slug()) }}" class="btn btn-primary">Nowe</a>
        @endif
    </div>

    @if($kind === \App\Domain\Training\TrainingKind::Challenge)
        <p class="text-text-muted text-sm mb-4">Wyzwanie składasz z ćwiczeń biblioteki (bez przerw). Bez pauzy i bez pomijania w aplikacji.</p>
    @endif

    @if($kind === \App\Domain\Training\TrainingKind::Exercise)
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm text-text-secondary">
                <thead class="bg-bg text-accent">
                    <tr>
                        <th class="text-left py-3 px-3 font-semibold">Ćwiczenie</th>
                        <th class="text-left py-3 px-3 font-semibold">Domyślnie</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(\App\Domain\Training\TrainingExerciseCatalog::all() as $key => $limits)
                        <tr class="border-t border-border/60">
                            <td class="py-3 px-3 text-text">{{ \App\Domain\Training\TrainingExerciseCatalog::label($key) }}</td>
                            <td class="py-3 px-3">
                                @if($limits['durationSeconds'])
                                    {{ $limits['durationSeconds'] }} s
                                @else
                                    {{ $limits['dartCount'] }} lotek
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-text-muted text-xs mt-3">Ekran każdego ćwiczenia ustalamy osobno. Do tego czasu szablon tylko istnieje.</p>
    @else
        @include('admin.training.partials.rows', ['heading' => 'Aktywne', 'rows' => $active])
        @include('admin.training.partials.rows', ['heading' => 'Nieaktywne', 'rows' => $inactive])
    @endif
</div>
@endsection
