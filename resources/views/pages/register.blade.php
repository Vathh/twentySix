@extends('layouts.app')

@section('title', 'Rejestracja')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[80vh] px-4">
        <div class="auth-card">
            <form action="{{ route('register') }}" method="POST">
                @csrf
                <div class="flex flex-col items-stretch">
                    <h1 class="page-title text-center">Rejestracja</h1>

                    <label class="form-label text-accent" for="name">Nazwa użytkownika</label>
                    <input class="mb-5 input-field"
                           type="text"
                           placeholder="Wprowadź nazwę użytkownika"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           required>

                    <label class="form-label text-accent" for="email">Email</label>
                    <input class="mb-5 input-field"
                           type="email"
                           placeholder="Wprowadź email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           required>

                    <label class="form-label text-accent" for="password">Hasło</label>
                    <input class="mb-5 input-field"
                           type="password"
                           placeholder="Wprowadź hasło"
                           name="password"
                           id="password"
                           required>

                    <label class="form-label text-accent" for="password_confirmation">Powtórz hasło</label>
                    <input class="mb-5 input-field"
                           type="password"
                           placeholder="Powtórz hasło"
                           name="password_confirmation"
                           id="password_confirmation"
                           required>

                    <button class="btn btn-primary mt-3" type="submit" name="loginBtn">Stwórz konto</button>

                    <x-errors/>
                </div>
            </form>
            <p class="text-accent mt-7 text-center">
                Masz już konto?
                <a href="{{ route('pages.loginPanel') }}" class="font-bold hover:underline">Zaloguj się</a>
            </p>
        </div>
    </div>
@endsection
