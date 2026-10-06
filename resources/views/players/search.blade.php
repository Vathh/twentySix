@extends('layouts.app')

@section('title', 'Szukaj graczy')

@section('content')
    <div
        class="player-search"
        x-data="playerSearch(@js([
            'searchUrl' => route('players.search'),
            'query' => $q,
            'players' => $players,
        ]))"
    >
        <header class="player-search-head">
            <p class="entity-eyebrow">Gracze</p>
            <h1 class="page-title">Szukaj graczy</h1>
            <p class="player-search-lead">Wpisz imię albo ksywę zarejestrowanego gracza.</p>
        </header>

        <form class="player-search-bar" action="{{ route('players.search') }}" method="GET" @submit.prevent="search()">
            <input
                type="text"
                name="q"
                x-model="query"
                placeholder="Nazwa gracza"
                class="input-field"
                autocomplete="off"
                enterkeyhint="search"
                aria-label="Nazwa gracza"
            >
            <button type="submit" class="btn btn-primary shrink-0 gap-2 disabled:opacity-60" :disabled="loading">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span x-text="loading ? 'Szukam…' : 'Szukaj'">Szukaj</span>
            </button>
        </form>

        <p class="player-search-error" x-show="error" x-cloak x-text="error" role="alert"></p>

        <div x-show="searched && !loading && results.length === 0" x-cloak>
            <x-empty-state title="Brak wyników">
                <p class="empty-panel-desc">Nie znaleziono graczy pasujących do „<span x-text="lastQuery"></span>”.</p>
            </x-empty-state>
        </div>

        <div x-show="searched && results.length > 0" x-cloak>
            <p class="player-search-meta" x-text="resultLabel()"></p>
            <ul class="player-search-list">
                <template x-for="player in results" :key="player.id">
                    <li>
                        <a class="player-search-hit" :href="player.url">
                            <span class="profile-hero-mono" aria-hidden="true" x-text="player.initials"></span>
                            <span class="player-search-hit-name" x-text="player.name"></span>
                        </a>
                    </li>
                </template>
            </ul>
        </div>
    </div>
@endsection
