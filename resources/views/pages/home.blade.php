@extends('layouts.app')

@section('title', 'Strona główna')

@section('content')

    @guest
    @include('pages.partials.home-download')
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
            @include('pages.partials.home-actions')
        </div>
    </div>
    @endguest

    @auth
    <div class="w-full max-w-xl mx-auto px-4 py-2">
        @include('pages.partials.home-download')
        @include('pages.partials.feed', ['feed' => $feed])
    </div>
    @endauth

@endsection
