@extends('layouts.app')

@section('title', 'Nowe hasło')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh] px-4">
        <div class="auth-card">
            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="flex flex-col items-stretch">
                    <h1 class="page-title text-center">Ustaw nowe hasło</h1>

                    <label class="form-label text-accent" for="email">Email</label>
                    <input class="mb-3 input-field"
                           type="email"
                           id="email"
                           name="email"
                           value="{{ old('email', $email) }}"
                           required>

                    <label class="form-label text-accent" for="password">Nowe hasło</label>
                    <input class="mb-3 input-field"
                           type="password"
                           id="password"
                           name="password"
                           placeholder="Co najmniej 8 znaków"
                           required>

                    <label class="form-label text-accent" for="password_confirmation">Powtórz hasło</label>
                    <input class="mb-3 input-field"
                           type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required>

                    <button class="btn btn-primary mt-3" type="submit">Zapisz hasło</button>

                    <x-errors/>
                </div>
            </form>
        </div>
    </div>
@endsection
