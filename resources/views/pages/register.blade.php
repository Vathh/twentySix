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
                    <input class="mb-3 input-field"
                           type="text"
                           placeholder="Wprowadź nazwę użytkownika"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           required>

                    <label class="form-label text-accent" for="email">Email</label>
                    <input class="mb-3 input-field"
                           type="email"
                           placeholder="Wprowadź email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           required>

                    <label class="form-label text-accent" for="password">Hasło</label>
                    <input class="mb-3 input-field"
                           type="password"
                           placeholder="Wprowadź hasło"
                           name="password"
                           id="password"
                           required>

                    <label class="form-label text-accent" for="password_confirmation">Powtórz hasło</label>
                    <input class="mb-3 input-field"
                           type="password"
                           placeholder="Powtórz hasło"
                           name="password_confirmation"
                           id="password_confirmation"
                           required>

                    <label class="flex items-start gap-2 mb-3 text-sm text-text-muted text-left">
                        <input class="mt-1"
                               type="checkbox"
                               name="acceptedTerms"
                               id="acceptedTerms"
                               value="1"
                               @checked(old('acceptedTerms'))
                               required>
                        <span>
                            Akceptuję
                            <a href="{{ route('pages.terms') }}" class="text-accent hover:underline" target="_blank" rel="noopener" @click.stop>regulamin</a>
                            i
                            <a href="{{ route('pages.privacy') }}" class="text-accent hover:underline" target="_blank" rel="noopener" @click.stop>politykę prywatności</a>.
                        </span>
                    </label>

                    <button class="btn btn-primary mt-3" type="submit" name="loginBtn">Stwórz konto</button>

                    <x-errors/>
                </div>
            </form>
            <p class="text-accent mt-5 text-center">
                Masz już konto?
                <a href="{{ route('pages.loginPanel') }}" class="font-bold hover:underline">Zaloguj się</a>
            </p>
        </div>
    </div>
@endsection
