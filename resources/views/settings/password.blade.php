@extends('layouts.app')

@section('title', 'Zmień hasło')

@section('content')
    <div class="detail-layout">
        @include('settings.partials.sidebar')

        <div class="detail-main">
            <div class="detail-content">
                <header class="entity-header">
                    <p class="entity-eyebrow">Ustawienia</p>
                    <h1 class="entity-title">Zmień hasło</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <form class="form-card !mx-0" action="{{ route('settings.password.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="flex flex-col items-stretch">
                        <label class="form-label text-accent" for="current_password">Aktualne hasło</label>
                        <input class="mb-5 input-field"
                               type="password"
                               id="current_password"
                               name="current_password"
                               autocomplete="current-password"
                               required>

                        <label class="form-label text-accent" for="password">Nowe hasło</label>
                        <input class="mb-5 input-field"
                               type="password"
                               id="password"
                               name="password"
                               autocomplete="new-password"
                               minlength="8"
                               required>

                        <label class="form-label text-accent" for="password_confirmation">Powtórz nowe hasło</label>
                        <input class="mb-5 input-field"
                               type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               autocomplete="new-password"
                               minlength="8"
                               required>

                        <button class="btn btn-primary mt-3" type="submit">Zapisz hasło</button>

                        <x-errors/>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
