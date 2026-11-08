@extends('layouts.app')

@section('title', 'Usuń konto')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh] px-4 py-8">
        <div class="auth-card">
            <form action="{{ route('account.delete') }}" method="POST">
                @csrf
                <div class="flex flex-col items-stretch">
                    <h1 class="page-title text-center">Usuń konto</h1>
                    <p class="text-sm text-text-muted leading-relaxed mb-4">
                        Znika e-mail, hasło, awatar, powiadomienia, komentarze i znajomi.
                        Wyniki meczów zostają, a profil w nich nazywa się „Gracz usunięty”.
                        Jeśli jesteś jedynym administratorem organizacji, najpierw wskaż innego.
                    </p>

                    <label class="form-label text-accent" for="email">Email</label>
                    <input class="mb-3 input-field"
                           type="email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           autocomplete="username"
                           required>

                    <label class="form-label text-accent" for="password">Hasło</label>
                    <input class="mb-3 input-field"
                           type="password"
                           name="password"
                           id="password"
                           autocomplete="current-password"
                           required>

                    <button class="btn btn-primary mt-3" type="submit">Usuń konto</button>

                    <x-errors/>
                </div>
            </form>
            <p class="text-sm text-text-muted mt-5 text-center">
                Szczegóły są w
                <a href="{{ route('pages.privacy') }}" class="text-accent hover:underline">polityce prywatności</a>.
            </p>
        </div>
    </div>
@endsection
