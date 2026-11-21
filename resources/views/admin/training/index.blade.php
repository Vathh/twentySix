@extends('layouts.app')

@section('title', 'Trening — panel')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-text-secondary text-sm hover:text-accent">← Panel</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">Trening</h1>
            <p class="text-text-secondary text-sm mt-2">Ćwiczenia są stałą listą. Plany, wyzwania i eventy powstają jako nieaktywne, aż je włączysz.</p>
        </div>
        <a href="{{ route('admin.push.index') }}" class="btn btn-secondary">Powiadomienia push</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach(\App\Domain\Training\TrainingKind::cases() as $kind)
            <a href="{{ route('admin.training.list', $kind->slug()) }}" class="card p-5 hover:border-accent/50">
                <h2 class="text-accent font-semibold mb-3">{{ $kind->plural() }}</h2>
                @if($kind === \App\Domain\Training\TrainingKind::Exercise)
                    <p class="text-sm text-text-secondary">Stała lista: <span class="text-text font-medium">{{ count(\App\Domain\Training\TrainingExerciseCatalog::all()) }}</span></p>
                    <p class="text-sm text-text-secondary mt-1">W planie zmieniasz czas albo liczbę lotek.</p>
                @else
                    <p class="text-sm text-text-secondary">Aktywne: <span class="text-text font-medium">{{ $counts[$kind->value]['active'] }}</span></p>
                    <p class="text-sm text-text-secondary mt-1">Nieaktywne: <span class="text-text font-medium">{{ $counts[$kind->value]['inactive'] }}</span></p>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection
