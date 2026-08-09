@extends('layouts.app')

@section('title', 'Logowanie tabletu')

@section('content')
<div class="max-w-lg mx-auto card p-6 text-center">
    <h1 class="text-2xl font-semibold text-accent mb-2">twentySix</h1>

    @if(!$tournament)
        <p class="text-text-secondary mb-4">Nieprawidłowy lub nieaktywny kod logowania tabletu.</p>
    @else
        <p class="text-text-secondary text-sm mb-1">Sędziowanie turnieju</p>
        <h2 class="text-xl text-text font-semibold mb-1">{{ $tournament->name }}</h2>
        @if($tournament->season?->league)
            <p class="text-text-secondary text-sm mb-4">{{ $tournament->season->league->name }}</p>
        @endif

        <p class="text-text-secondary text-sm mb-6">
            Otwórz aplikację twentySix na tablecie i zaloguj się kodem sędziowskim, aby wpisywać wyniki meczów.
        </p>

        <p class="text-accent font-mono text-2xl tracking-widest mb-6">{{ $code }}</p>

        <a href="{{ $appDeepLink }}" class="btn btn-primary inline-block mb-3">
            Otwórz w aplikacji
        </a>

        @if(config('mobile.apk_download_url'))
            <div class="mb-3">
                <a
                    href="{{ config('mobile.apk_download_url') }}"
                    class="btn btn-secondary inline-block"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Pobierz aplikację mobilną
                </a>
            </div>
        @endif

        <p class="text-text-secondary text-xs mt-4">
            Jeśli przycisk nie działa: uruchom twentySix → Turniej → wpisz kod <strong>{{ $code }}</strong> albo zeskanuj QR.
        </p>
    @endif
</div>
@endsection
