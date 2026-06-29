@extends('layouts.app')

@section('title', 'Live — '.$player1->name.' vs '.$player2->name)

@section('content')
    @php
        $liveP1 = $initialState['players'][0] ?? [];
        $liveP2 = $initialState['players'][1] ?? [];
        $liveLeg = $initialState['currentLeg'] ?? null;
        $liveLegLabel = $liveLeg
            ? 'Leg '.($liveLeg['legNumber'] ?? '?')
            : 'Brak otwartego lega';
    @endphp
    <div
        class="container mx-auto py-6 max-w-3xl"
        x-data="gameLiveViewer(@js([
            'initialState' => $initialState,
            'channel' => $broadcastChannel,
            'stateUrl' => $liveStateUrl,
            'reverb' => $reverb,
        ]))"
        x-init="init()"
    >
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <a href="{{ $backUrl }}" class="text-light-green hover:underline text-sm">← Powrót</a>
            <a href="{{ route('games.show', ['type' => $kind, 'id' => $gameId]) }}" class="text-light-orange hover:underline text-sm">
                Szczegóły meczu
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-light-green/20 text-light-green border border-light-green/40">
                {{ $label }}
            </span>
            @if($subtitle)
                <span class="text-text-muted text-sm">{{ $subtitle }}</span>
            @endif
            <span
                class="px-2 py-0.5 rounded text-xs font-semibold bg-light-orange/25 text-light-orange"
                x-bind:class="{
                    'bg-light-orange/25 text-light-orange animate-pulse': isLive,
                    'bg-light-green/20 text-light-green': isFinished,
                    'bg-dark-bg text-text-muted': !isLive && !isFinished,
                }"
                x-text="isLive ? 'W trakcie' : (isFinished ? 'Zakończony' : '{{ $status }}')"
            >{{ $status === 'in_progress' ? 'W trakcie' : ($status === 'finished' ? 'Zakończony' : $status) }}</span>
            <span
                class="px-2 py-0.5 rounded text-xs border border-border text-text-muted"
                x-text="connectionLabel()"
            >Łączenie…</span>
        </div>

        <h1 class="text-xl font-bold text-light-green mb-6 text-center">
            <span x-text="player1?.name">{{ $liveP1['name'] ?? $player1->name }}</span>
            <span class="text-text-muted font-normal mx-2">vs</span>
            <span x-text="player2?.name">{{ $liveP2['name'] ?? $player2->name }}</span>
        </h1>

        {{-- Zakładki --}}
        <div class="flex gap-1 mb-4 border-b border-border">
            <button
                type="button"
                x-on:click="tab = 'counter'"
                x-bind:class="tab === 'counter' ? 'text-light-orange border-light-orange' : 'text-text-muted border-transparent'"
                class="px-4 py-2 text-sm font-semibold border-b-2 transition"
            >
                Wynik
            </button>
            <button
                type="button"
                x-on:click="tab = 'stats'"
                x-bind:class="tab === 'stats' ? 'text-light-orange border-light-orange' : 'text-text-muted border-transparent'"
                class="px-4 py-2 text-sm font-semibold border-b-2 transition"
            >
                Statystyki
            </button>
        </div>

        {{-- Zakładka: Wynik (jak tablet, bez klawiatury) --}}
        <div x-show="tab === 'counter'">
            {{-- Legi --}}
            <div class="grid grid-cols-3 items-center gap-4 mb-6 text-center">
                <div>
                    <p class="text-sm text-text-muted mb-1" x-text="player1?.name">{{ $liveP1['name'] ?? $player1->name }}</p>
                    <p class="text-4xl font-bold text-light-green" x-text="player1?.legsWon ?? 0">{{ $liveP1['legsWon'] ?? $player1Score }}</p>
                </div>
                <div>
                    <p class="text-xs text-light-orange uppercase tracking-wide mb-1">Legi</p>
                    <p class="text-lg text-text-muted" x-text="currentLegLabel">{{ $liveLegLabel }}</p>
                    <p class="text-xs text-text-muted mt-1">
                        do <span x-text="state?.game?.legsToWin ?? 2">{{ $legsToWin ?? 2 }}</span> wygranych
                    </p>
                </div>
                <div>
                    <p class="text-sm text-text-muted mb-1" x-text="player2?.name">{{ $liveP2['name'] ?? $player2->name }}</p>
                    <p class="text-4xl font-bold text-light-green" x-text="player2?.legsWon ?? 0">{{ $liveP2['legsWon'] ?? $player2Score }}</p>
                </div>
            </div>

            {{-- Pozostało w legu --}}
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-lighter-bg rounded-xl p-6 text-center border border-border">
                    <p class="text-xs text-text-muted mb-2">Pozostało</p>
                    <p class="text-5xl font-bold text-light-white tabular-nums" x-text="player1?.remaining ?? '—'">{{ $liveP1['remaining'] ?? '—' }}</p>
                </div>
                <div class="bg-lighter-bg rounded-xl p-6 text-center border border-border">
                    <p class="text-xs text-text-muted mb-2">Pozostało</p>
                    <p class="text-5xl font-bold text-light-white tabular-nums" x-text="player2?.remaining ?? '—'">{{ $liveP2['remaining'] ?? '—' }}</p>
                </div>
            </div>

            {{-- Wizyty bieżącego lega --}}
            <div class="bg-darker-bg rounded-lg border border-border p-4">
                <h2 class="text-sm font-semibold text-light-orange mb-3">Wizyty w bieżącym legu</h2>
                <template x-if="visits.length === 0">
                    <p class="text-text-muted text-sm">Brak wizyt w tym legu.</p>
                </template>
                <div class="grid md:grid-cols-2 gap-4" x-show="visits.length > 0">
                    <div>
                        <h3 class="text-sm font-semibold text-light-green mb-2" x-text="player1?.name">{{ $liveP1['name'] ?? $player1->name }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-light-white">
                                <thead>
                                    <tr class="text-text-muted text-xs border-b border-border">
                                        <th class="text-left py-2 pr-2">#</th>
                                        <th class="text-center py-2 px-2">Pkt</th>
                                        <th class="text-center py-2 px-2">Zostało</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="visit in visitsForPlayer(player1?.playerId)" x-bind:key="visit.id">
                                        <tr class="border-b border-border/40" x-bind:class="visit.bust ? 'opacity-60' : ''">
                                            <td class="py-2 pr-2" x-text="visit.visitNumber"></td>
                                            <td class="text-center py-2 px-2" x-text="visit.bust ? 'Bust' : visit.score"></td>
                                            <td class="text-center py-2 px-2" x-text="visit.remainingAfter"></td>
                                        </tr>
                                    </template>
                                    <template x-if="visitsForPlayer(player1?.playerId).length === 0">
                                        <tr>
                                            <td colspan="3" class="py-2 text-text-muted text-sm text-center">Brak wizyt</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-light-green mb-2" x-text="player2?.name">{{ $liveP2['name'] ?? $player2->name }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-light-white">
                                <thead>
                                    <tr class="text-text-muted text-xs border-b border-border">
                                        <th class="text-left py-2 pr-2">#</th>
                                        <th class="text-center py-2 px-2">Pkt</th>
                                        <th class="text-center py-2 px-2">Zostało</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="visit in visitsForPlayer(player2?.playerId)" x-bind:key="visit.id">
                                        <tr class="border-b border-border/40" x-bind:class="visit.bust ? 'opacity-60' : ''">
                                            <td class="py-2 pr-2" x-text="visit.visitNumber"></td>
                                            <td class="text-center py-2 px-2" x-text="visit.bust ? 'Bust' : visit.score"></td>
                                            <td class="text-center py-2 px-2" x-text="visit.remainingAfter"></td>
                                        </tr>
                                    </template>
                                    <template x-if="visitsForPlayer(player2?.playerId).length === 0">
                                        <tr>
                                            <td colspan="3" class="py-2 text-text-muted text-sm text-center">Brak wizyt</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Zakładka: Statystyki --}}
        <div x-show="tab === 'stats'" x-cloak>
            <div class="bg-lighter-bg rounded-lg border border-border overflow-hidden">
                <div class="grid grid-cols-3 bg-dark-bg text-xs text-text-muted uppercase tracking-wide">
                    <div class="p-3"></div>
                    <div class="p-3 text-center font-semibold" x-text="player1?.name"></div>
                    <div class="p-3 text-center font-semibold" x-text="player2?.name"></div>
                </div>

                <template x-for="row in [
                    { label: 'Średnia (leg)', key: 'legAverage' },
                    { label: 'Średnia (mecz)', key: 'gameAverage' },
                    { label: 'Średnia (9 lotek)', key: 'firstNineAverage' },
                    { label: 'Double %', key: 'doublePercent', format: 'percent' },
                ]" x-bind:key="row.label">
                    <div class="grid grid-cols-3 border-t border-border text-sm">
                        <div class="p-3 text-text-muted" x-text="row.label"></div>
                        <div class="p-3 text-center text-light-white"
                             x-text="row.format === 'percent' ? formatPercent(player1?.[row.key]) : formatAverage(player1?.[row.key])"></div>
                        <div class="p-3 text-center text-light-white"
                             x-text="row.format === 'percent' ? formatPercent(player2?.[row.key]) : formatAverage(player2?.[row.key])"></div>
                    </div>
                </template>
            </div>

            <template x-if="(state?.legs ?? []).length > 0">
                <div class="mt-6 bg-darker-bg rounded-lg border border-border p-4">
                    <h2 class="text-sm font-semibold text-light-orange mb-3">Zakończone legi</h2>
                    <ul class="space-y-2 text-sm text-light-white">
                        <template x-for="leg in state.legs" x-bind:key="leg.id">
                            <li class="flex justify-between border-b border-border/30 pb-1">
                                <span x-text="'Leg ' + leg.legNumber"></span>
                                <span class="text-light-green" x-text="playerName(leg.winnerId)"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
@endsection
