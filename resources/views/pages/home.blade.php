@extends('layouts.app')

@section('title', 'Strona główna')

@section('content')

    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="home-hero">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-accent mb-4">twentySix</p>
            <h2 class="text-4xl font-bold text-text mb-4 tracking-tight">Ligi, turnieje, wyniki na żywo</h2>
            <p class="text-lg mb-10 text-text-secondary max-w-md mx-auto">
                Śledź rankingi i rozgrywki — wszystko w jednym miejscu.
            </p>
            <a href="/tournaments" class="btn btn-primary">Zobacz turnieje</a>
        </div>
    </div>

@endsection
