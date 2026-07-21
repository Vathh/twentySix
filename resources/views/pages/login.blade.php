@extends('layouts.app')

@section('title', 'Logowanie')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="auth-card">
            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="flex flex-col items-stretch">
                    <h1 class="page-title text-center">Zaloguj się</h1>

                    <label class="form-label text-accent" for="login">Email</label>
                    <input class="mb-5 input-field"
                           type="email"
                           placeholder="Wprowadź email"
                           name="email"
                           value="{{ old('email') }}"
                           required>

                    <label class="form-label text-accent" for="password">Hasło</label>
                    <input class="mb-5 input-field"
                           type="password"
                           placeholder="Wprowadź hasło"
                           name="password"
                           id="password"
                           required>

                    <button class="btn btn-primary mt-3" type="submit" name="loginBtn">Zaloguj</button>

                    <x-errors/>
                </div>
            </form>
            <p class="text-accent mt-7 text-center">
                Nie masz jeszcze konta?
                <a href="{{ route('pages.registerPanel') }}" class="font-bold hover:underline">Zarejestruj się</a>
            </p>
        </div>
    </div>
@endsection
