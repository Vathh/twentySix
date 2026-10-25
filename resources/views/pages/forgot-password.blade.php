@extends('layouts.app')

@section('title', 'Nie pamiętam hasła')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="auth-card">
            <form action="{{ route('password.email') }}" method="POST">
                @csrf
                <div class="flex flex-col items-stretch">
                    <h1 class="page-title text-center">Nie pamiętam hasła</h1>
                    <p class="text-text-secondary text-sm text-center mb-4">
                        Podaj email konta. Wyślemy link do ustawienia nowego hasła.
                    </p>

                    <label class="form-label text-accent" for="email">Email</label>
                    <input class="mb-3 input-field"
                           type="email"
                           id="email"
                           placeholder="Wprowadź email"
                           name="email"
                           value="{{ old('email') }}"
                           required>

                    <button class="btn btn-primary mt-3" type="submit">Wyślij link</button>

                    <x-errors/>
                </div>
            </form>
            <p class="text-accent mt-5 text-center">
                <a href="{{ route('pages.loginPanel') }}" class="font-bold hover:underline">Wróć do logowania</a>
            </p>
        </div>
    </div>
@endsection
