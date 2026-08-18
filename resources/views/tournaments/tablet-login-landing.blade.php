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
        @if($tournament->season?->organization)
            <p class="text-text-secondary text-sm mb-4">{{ $tournament->season->organization->name }}</p>
        @endif

        <p class="text-text-secondary text-sm mb-6">
            Sędziuj mecz w przeglądarce (laptop) albo w aplikacji twentySix na tablecie — ten sam kod.
        </p>

        <p class="text-accent font-mono text-2xl tracking-widest mb-6">{{ $code }}</p>

        <a
            href="{{ route('referee.login', ['code' => $code, 'auto' => 1]) }}"
            class="btn btn-primary inline-block mb-3"
        >
            Sędziuj w przeglądarce
        </a>

        <a href="{{ $appDeepLink }}" class="btn btn-secondary inline-block mb-3">
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
            Aplikacja: twentySix → Turniej → kod <strong>{{ $code }}</strong> albo QR.
            Web: możesz też wejść na
            <a href="{{ route('referee.login') }}" class="text-accent hover:underline">/referee/login</a>.
        </p>
    @endif
</div>
@endsection
