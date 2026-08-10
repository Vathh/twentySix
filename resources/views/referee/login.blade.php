@extends('referee.layout')

@section('title', 'Kod sędziowski')

@section('content')
<div
    class="card p-6 max-w-md mx-auto"
    x-data="refereeLogin(@js([
        'loginUrl' => url('/api/login'),
        'gamesUrl' => route('referee.games'),
        'initialCode' => request()->query('code', ''),
        'autoSubmit' => request()->boolean('auto'),
    ]))"
    x-init="init()"
>
    <h1 class="text-xl font-semibold text-accent mb-2">Sędziowanie w przeglądarce</h1>
    <p class="text-text-secondary text-sm mb-6">
        Wpisz kod logowania tabletu z turnieju (ten sam co w aplikacji mobilnej).
    </p>

    <label class="block text-sm text-text-muted mb-1" for="referee-code">Kod</label>
    <input
        id="referee-code"
        type="text"
        class="input-field w-full mb-4 font-mono tracking-widest uppercase"
        maxlength="16"
        autocomplete="off"
        autocapitalize="characters"
        x-model="code"
        @keydown.enter.prevent="submit()"
        :disabled="busy"
    >

    <p class="text-danger text-sm mb-3" x-show="error" x-text="error" x-cloak></p>

    <button
        type="button"
        class="btn btn-primary w-full"
        @click="submit()"
        :disabled="busy || code.trim().length < 4"
    >
        <span x-show="!busy">Wejdź</span>
        <span x-show="busy" x-cloak>Logowanie…</span>
    </button>

    <p class="text-text-muted text-xs mt-4 text-center">
        Kod znajdziesz na stronie turnieju (sekcja dla organizatora).
    </p>
</div>
@endsection
