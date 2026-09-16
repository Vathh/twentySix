@extends('layouts.app')

@section('title', 'Strona główna')

@section('content')

    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="home-hero">
            <img
                src="{{ asset('images/logotyp.svg') }}"
                alt=""
                class="home-hero-mark"
                width="920"
                height="580"
                aria-hidden="true"
            >
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent mb-2">twentySix</p>
            <h2 class="text-lg sm:text-xl font-bold text-text mb-2 tracking-tight">Organizacje, turnieje, wyniki na żywo</h2>
            <p class="text-xs sm:text-sm mb-4 sm:mb-5 text-text-secondary max-w-md mx-auto">
                Śledź rankingi i rozgrywki — wszystko w jednym miejscu.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center items-stretch sm:items-center">
                <a href="/tournaments" class="btn btn-primary">Zobacz turnieje</a>
                <a href="{{ route('referee.login') }}" class="btn btn-secondary">Sędziowanie turnieju</a>
            </div>
            @if(config('mobile.apk_download_url'))
                <div class="mt-3 flex justify-center">
                    <a
                        href="{{ config('mobile.apk_download_url') }}"
                        class="btn btn-secondary"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Pobierz aplikację mobilną
                    </a>
                </div>
            @endif
        </div>
    </div>

@endsection
