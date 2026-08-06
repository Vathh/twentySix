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
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-accent mb-4">twentySix</p>
            <h2 class="text-2xl sm:text-4xl font-bold text-text mb-4 tracking-tight">Ligi, turnieje, wyniki na żywo</h2>
            <p class="text-base sm:text-lg mb-8 sm:mb-10 text-text-secondary max-w-md mx-auto">
                Śledź rankingi i rozgrywki — wszystko w jednym miejscu.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center items-stretch sm:items-center">
                <a href="/tournaments" class="btn btn-primary">Zobacz turnieje</a>
                @if(config('mobile.apk_download_url'))
                    <a
                        href="{{ config('mobile.apk_download_url') }}"
                        class="btn btn-secondary"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Pobierz aplikację mobilną
                    </a>
                @endif
            </div>
        </div>
    </div>

@endsection
