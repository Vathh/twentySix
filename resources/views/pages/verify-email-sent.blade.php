@extends('layouts.app')

@section('title', 'Potwierdź email')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh]">
        <div class="container flex justify-center items-center flex-grow flex-1">
            <div class="inline-flex flex-col justify-center items-center bg-lighter-bg rounded-2xl p-12 max-w-lg text-center">
                <h1 class="text-light-green mb-6 text-2xl font-bold">Sprawdź skrzynkę email</h1>

                @if(session('success'))
                    <p class="text-light-white mb-4">{{ session('success') }}</p>
                @endif

                @if(session('registered_email'))
                    <p class="text-light-orange mb-6">
                        Wysłaliśmy link potwierdzający na adres
                        <strong class="text-light-white">{{ session('registered_email') }}</strong>.
                    </p>
                @else
                    <p class="text-light-orange mb-6">
                        Kliknij link w wiadomości od twentySix, aby aktywować konto.
                    </p>
                @endif

                <p class="text-light-gray text-sm mb-8">
                    Po potwierdzeniu możesz się zalogować. Link jest ważny przez 60 minut.
                </p>

                @if(session('registered_email'))
                    <form action="{{ route('verification.send') }}" method="POST" class="mb-6">
                        @csrf
                        <input type="hidden" name="email" value="{{ session('registered_email') }}">
                        <button type="submit" class="btn btn-mini">Wyślij link ponownie</button>
                    </form>
                @endif

                <a href="{{ route('pages.loginPanel') }}" class="text-light-orange font-bold hover:text-light-green transition">
                    Przejdź do logowania
                </a>
            </div>
        </div>
    </div>
@endsection
