@extends('layouts.app')

@section('title', 'Potwierdź email')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="auth-card text-center">
            <h1 class="page-title">Sprawdź skrzynkę email</h1>

            @if(session('success'))
                <p class="text-text-secondary mb-4">{{ session('success') }}</p>
            @endif

            @if(session('registered_email'))
                <p class="text-accent mb-6">
                    Wysłaliśmy link potwierdzający na adres
                    <strong class="text-text-secondary">{{ session('registered_email') }}</strong>.
                </p>
            @else
                <p class="text-accent mb-6">
                    Kliknij link w wiadomości od twentySix, aby aktywować konto.
                </p>
            @endif

            <p class="text-text-muted text-sm mb-8">
                Po potwierdzeniu możesz się zalogować. Link jest ważny przez 60 minut.
            </p>

            @if(session('registered_email'))
                <form action="{{ route('verification.send') }}" method="POST" class="mb-6">
                    @csrf
                    <input type="hidden" name="email" value="{{ session('registered_email') }}">
                    <button type="submit" class="btn btn-mini">Wyślij link ponownie</button>
                </form>
            @endif

            <a href="{{ route('pages.loginPanel') }}" class="text-accent font-bold hover:underline">
                Przejdź do logowania
            </a>
        </div>
    </div>
@endsection
