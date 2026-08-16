@extends('layouts.app')

@section('title', 'Live — szybki mecz FFA')

@section('content')
    @php
        $livePlayers = $initialState['players'] ?? [];
        $liveLeg = $initialState['currentLeg'] ?? null;
        $liveIsCricket = strtolower((string) ($initialState['session']['gameType'] ?? '')) === 'cricket';
    @endphp
    <div
        class="container mx-auto py-6 max-w-4xl"
        x-data="ffaLiveViewer(@js([
            'initialState' => $initialState,
            'channel' => $broadcastChannel,
            'stateUrl' => $liveStateUrl,
            'showUrl' => $showUrl,
            'reverb' => $reverb,
        ]))"
        x-init="init()"
    >
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <a href="{{ route('pages.home') }}" class="link-back">← Strona główna</a>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold badge-live">
                Szybki mecz · FFA
            </span>
            <span
                class="px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent"
                x-bind:class="{
                    'bg-accent/25 text-accent animate-pulse': isLive,
                    'bg-success-muted text-success-bright': isFinished,
                    'bg-bg text-text-muted': !isLive && !isFinished,
                }"
                x-text="isLive ? 'W trakcie' : (isFinished ? 'Zakończony' : '—')"
            >W trakcie</span>
            <span
                class="px-2 py-0.5 rounded text-xs border border-border text-text-muted"
                x-text="connectionLabel()"
            >Łączenie…</span>
        </div>

        <h1 class="text-xl font-bold text-accent mb-2 text-center">Szybki mecz FFA</h1>
        @if(!empty($formatLabel))
            <p class="text-center text-text-muted text-sm mb-4">{{ $formatLabel }}</p>
        @endif

        <div
            x-show="connection === 'connecting' || connection === 'reconnecting'"
            x-cloak
            class="live-connecting-banner"
        >
            <div class="skeleton h-3 w-3 rounded-full shrink-0"></div>
            <div class="flex-1 space-y-2 min-w-0">
                <div class="skeleton h-2.5 w-40 max-w-full"></div>
                <div class="skeleton h-2 w-28 max-w-full"></div>
            </div>
            <span class="shrink-0 text-xs" x-text="connectionLabel()">Łączenie…</span>
        </div>

        <div class="flex gap-1 mb-4 border-b border-border">
            <button
                type="button"
                x-on:click="tab = 'counter'"
                x-bind:class="tab === 'counter' ? 'text-accent border-accent' : 'text-text-muted border-transparent'"
                class="px-4 py-2 text-sm font-semibold border-b-2 transition"
            >
                Wynik
            </button>
            <button
                type="button"
                x-on:click="tab = 'visits'"
                x-bind:class="tab === 'visits' ? 'text-accent border-accent' : 'text-text-muted border-transparent'"
                class="px-4 py-2 text-sm font-semibold border-b-2 transition"
            >
                Wizyty
            </button>
            <button
                type="button"
                x-on:click="tab = 'stats'"
                x-bind:class="tab === 'stats' ? 'text-accent border-accent' : 'text-text-muted border-transparent'"
                class="px-4 py-2 text-sm font-semibold border-b-2 transition"
            >
                Statystyki
            </button>
        </div>

        <div x-show="tab === 'counter'">
            <p class="text-center text-accent font-semibold mb-4" x-text="currentLegLabel">
                {{ $liveLeg ? ('Leg '.$liveLeg['legNumber']) : '—' }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2 mb-6">
                <template x-for="(player, index) in players" :key="player.playerId">
                    <div
                        class="rounded-xl p-4 border bg-bg-elevated relative overflow-hidden"
                        x-bind:class="isCurrentTurn(player, index)
                            ? 'border-accent ring-1 ring-accent/40'
                            : 'border-border'"
                    >
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-text-secondary truncate" x-text="player.name"></p>
                                <template x-if="isCurrentTurn(player, index)">
                                    <p class="text-xs text-accent font-semibold mt-0.5">Teraz rzuca</p>
                                </template>
                            </div>
                            <p class="text-2xl font-bold text-text score-num shrink-0" x-text="matchScore(player)"></p>
                        </div>
                        <template x-if="!isCricket && !isBob27">
                            <div>
                                <p class="text-xs text-text-muted mb-1">Pozostało</p>
                                <p class="text-4xl font-bold text-text-secondary score-num" x-text="player.remaining ?? '—'"></p>
                            </div>
                        </template>
                        <template x-if="isCricket">
                            <div>
                                <p class="text-xs text-text-muted mb-1">Punkty (krykiet)</p>
                                <p class="text-4xl font-bold text-text-secondary score-num"
                                   x-text="player.points ?? player.cricketPoints ?? '—'"></p>
                            </div>
                        </template>
                        <template x-if="isBob27">
                            <div>
                                <p class="text-xs text-text-muted mb-1">Bob's 27</p>
                                <p class="text-4xl font-bold text-text-secondary score-num"
                                   x-text="player.score ?? '—'"></p>
                                <p class="text-xs text-text-muted mt-1"
                                   x-text="player.eliminated ? 'Wypadł' : currentTargetLabel"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            @if(count($livePlayers) === 0)
                <p class="text-text-muted text-sm text-center">Brak graczy w stanie meczu.</p>
            @endif
        </div>

        <div x-show="tab === 'visits'" x-cloak>
            <div class="bg-bg-deep rounded-lg border border-border p-4">
                <h2 class="text-sm font-semibold text-accent mb-3">Wizyty w bieżącym legu</h2>
                <template x-if="isCricket">
                    <p class="text-text-muted text-sm">Dla krykieta lista wizyt X01 nie jest dostępna w tym widoku.</p>
                </template>
                <template x-if="isBob27">
                    <p class="text-text-muted text-sm">Dla Bob's 27 lista wizyt X01 nie jest dostępna w tym widoku.</p>
                </template>
                <template x-if="!isCricket && !isBob27 && visits.length === 0">
                    <p class="text-text-muted text-sm">Brak wizyt w tym legu.</p>
                </template>
                <div class="grid sm:grid-cols-2 gap-4" x-show="!isCricket && !isBob27 && visits.length > 0">
                    <template x-for="player in players" :key="'v-'+player.playerId">
                        <div>
                            <h3 class="text-sm font-semibold text-accent mb-2" x-text="player.name"></h3>
                            <ul class="space-y-1 text-sm text-text-secondary">
                                <template x-for="visit in visitsForPlayer(player.playerId)" :key="visit.id">
                                    <li class="flex justify-between gap-2 border-b border-border/50 py-1">
                                        <span x-text="'#' + visit.visitNumber"></span>
                                        <span class="score-num font-semibold"
                                              x-bind:class="visit.bust ? 'text-danger-text' : ''"
                                              x-text="visit.bust ? 'BUST' : visit.score"></span>
                                    </li>
                                </template>
                                <template x-if="visitsForPlayer(player.playerId).length === 0">
                                    <li class="text-text-muted">—</li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="tab === 'stats'" x-cloak>
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm text-text-secondary">
                    <thead class="bg-bg-elevated text-accent">
                        <tr>
                            <th class="text-left py-2 px-3">Gracz</th>
                            <th class="text-right py-2 px-3">Wynik</th>
                            <th class="text-right py-2 px-3">Avg</th>
                            <th class="text-right py-2 px-3">Leg avg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="player in players" :key="'s-'+player.playerId">
                            <tr class="border-t border-border">
                                <td class="py-2 px-3 font-semibold text-text" x-text="player.name"></td>
                                <td class="py-2 px-3 text-right score-num" x-text="matchScore(player)"></td>
                                <td class="py-2 px-3 text-right score-num" x-text="formatAverage(player.gameAverage)"></td>
                                <td class="py-2 px-3 text-right score-num" x-text="formatAverage(player.legAverage)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
