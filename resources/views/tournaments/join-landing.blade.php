@extends('layouts.app')

@section('title', 'Dołącz do turnieju')

@section('content')
<div class="max-w-lg mx-auto card p-6 text-center">
    <h1 class="text-2xl font-semibold text-accent mb-2">twentySix</h1>

    @if(!$tournament)
        <p class="text-text-secondary mb-4">Nieprawidłowy lub nieaktywny kod turnieju.</p>
    @else
        <p class="text-text-secondary text-sm mb-1">Zgłoszenie do turnieju</p>
        <h2 class="text-xl text-text font-semibold mb-1">{{ $tournament->name }}</h2>
        @if($tournament->season?->league)
            <p class="text-text-secondary text-sm mb-4">{{ $tournament->season->league->name }}</p>
        @endif

        <p class="text-text-secondary text-sm mb-6">
            Otwórz aplikację twentySix i zgłoś udział. Organizator zatwierdzi Twoje zgłoszenie na liście startowej.
        </p>

        <p class="text-accent font-mono text-2xl tracking-widest mb-6">{{ $code }}</p>

        <a href="{{ $appDeepLink }}" class="btn btn-primary inline-block mb-3">
            Otwórz w aplikacji
        </a>

        <p class="text-text-secondary text-xs mt-4">
            Jeśli przycisk nie działa: uruchom twentySix → wpisz kod <strong>{{ $code }}</strong> w „Dołącz do turnieju”.
        </p>
    @endif
</div>
@endsection
