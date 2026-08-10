@extends('referee.layout')

@section('title', 'Mecze')

@section('content')
<div
    x-data="refereeGames(@js([
        'scoreUrl' => route('referee.score'),
    ]))"
    x-init="init()"
>
    <div class="flex items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-semibold text-accent">Mecze turnieju</h1>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="btn btn-secondary !py-2 !px-3 text-sm"
                @click="fetchGames()"
                :disabled="loading"
            >
                Odśwież
            </button>
            <button
                type="button"
                class="btn btn-secondary !py-2 !px-3 text-sm"
                @click="logout()"
            >
                Wyloguj
            </button>
        </div>
    </div>

    <p class="text-danger text-sm mb-3" x-show="error" x-text="error" x-cloak></p>

    <div x-show="loading" class="text-text-muted text-sm py-8 text-center">
        Ładowanie meczów…
    </div>

    <template x-if="!loading && groupGames.length === 0 && playoffGames.length === 0">
        <p class="text-text-muted text-sm">Brak aktywnych meczów.</p>
    </template>

    <div x-show="!loading && groups.length > 0" class="mb-6" x-cloak>
        <h2 class="text-sm font-semibold text-accent mb-2">Faza grupowa</h2>
        <div class="grid gap-2">
            <template x-for="group in groups" :key="group">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition"
                    @click="openGroup(group)"
                >
                    <span class="font-semibold text-text">Grupa </span>
                    <span class="font-semibold text-accent" x-text="group"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-show="!loading && playoffGames.length > 0" x-cloak>
        <h2 class="text-sm font-semibold text-accent mb-2">Playoff</h2>
        <div class="grid gap-2">
            <template x-for="game in playoffGames" :key="'p-'+game.id">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition disabled:opacity-50"
                    @click="startGame(game)"
                    :disabled="lockingId != null"
                >
                    <div class="text-xs text-text-muted mb-0.5" x-text="game.roundLabel || game.round || 'Playoff'"></div>
                    <div class="font-semibold text-text" x-text="playerLabel(game)"></div>
                    <div class="text-xs text-accent mt-1" x-show="lockingId === ('playoff-'+game.id)">Blokowanie…</div>
                </button>
            </template>
        </div>
    </div>

    <div
        x-show="selectedGroup != null"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 p-4"
        @keydown.escape.window="closeGroup()"
        @click.self="closeGroup()"
    >
        <div class="w-full max-w-md rounded-xl border border-border bg-bg-deep shadow-xl p-4" @click.stop>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-accent">
                    Grupa <span x-text="selectedGroup"></span>
                </h3>
                <button type="button" class="text-text-muted hover:text-accent text-xl leading-none" @click="closeGroup()" aria-label="Zamknij">✕</button>
            </div>
            <div class="grid gap-2 max-h-[60vh] overflow-y-auto">
                <template x-for="game in gamesInSelectedGroup" :key="'g-'+game.id">
                    <button
                        type="button"
                        class="w-full text-left px-4 py-3 rounded-lg bg-bg-elevated border border-border hover:border-accent/40 transition disabled:opacity-50"
                        @click="startGame(game)"
                        :disabled="lockingId != null"
                    >
                        <div class="font-semibold text-text" x-text="playerLabel(game)"></div>
                        <div class="text-xs text-accent mt-1" x-show="lockingId === ('group-'+game.id)">Blokowanie…</div>
                    </button>
                </template>
                <p class="text-text-muted text-sm" x-show="gamesInSelectedGroup.length === 0">Brak meczów w grupie.</p>
            </div>
        </div>
    </div>
</div>
@endsection
