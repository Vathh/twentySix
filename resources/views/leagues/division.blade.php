@extends('layouts.app')

@section('title', $division->name.' · '.$league->name)

@section('content')
    @php
        $defaultSeasonId = $seasons[0]['season']->id ?? null;
        $openSeason = $league->seasons->first(fn ($season) => $season->status->isOpen());
        $playerName = function (array $tab, int $playerId): string {
            $participant = $tab['players']->get($playerId);

            return $participant?->player?->name ?? ('#'.$playerId);
        };
    @endphp
    <div @if(count($seasons) > 1) x-data="{ activeSeason: {{ (int) $defaultSeasonId }} }" @endif>
        <div class="detail-layout">
            <div class="detail-main">
                <div class="detail-content">
                    <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>

                    <header class="entity-header">
                        <p class="entity-eyebrow">Szczebel ligowy · {{ $league->name }}</p>
                        <h1 class="entity-title">{{ $division->name }}</h1>
                        <span class="entity-rule" aria-hidden="true"></span>
                    </header>

                    <p class="text-text-muted mb-8">
                        {{ $division->starting_score }}
                        · do {{ $division->legs_to_win_set }} {{ $division->sets_to_win_match > 1 ? 'legów / '.$division->sets_to_win_match.' setów' : 'legów' }}
                        @if($division->position > 0)
                            · awans: {{ $division->promote_direct }} bezpośredni
                            @if($division->promote_playoff > 0)
                                + {{ $division->promote_playoff }} baraż
                            @endif
                        @endif
                    </p>

                    @if($openSeason)
                        <p class="text-text-muted mb-6">
                            Trwa sezon
                            <a href="{{ route('league-seasons.show', $openSeason) }}" class="text-accent font-semibold hover:underline">{{ $openSeason->name }}</a>
                            — poniżej archiwum zakończonych sezonów.
                        </p>
                    @endif

                    @if(count($seasons) === 0)
                        <x-empty-state
                            class="!py-10"
                            title="Brak zakończonych sezonów"
                            description="Tabela pojawi się po zakończeniu pierwszego sezonu na tym szczeblu. Bieżące rozgrywki są na stronie sezonu."
                        />
                    @else
                        @if(count($seasons) > 1)
                            <div class="overflow-x-auto -mx-1 px-1 mb-6 mt-4" role="tablist" aria-label="Zakończone sezony">
                                <div class="flex border-b border-border min-w-max">
                                    @foreach($seasons as $tab)
                                        <button
                                            type="button"
                                            role="tab"
                                            @click="activeSeason = {{ $tab['season']->id }}"
                                            :aria-selected="activeSeason === {{ $tab['season']->id }}"
                                            :class="activeSeason === {{ $tab['season']->id }}
                                                ? 'border-accent text-accent'
                                                : 'border-transparent text-text-muted hover:text-accent'"
                                            class="px-4 sm:px-5 py-3 text-sm font-semibold transition border-b-2 -mb-px whitespace-nowrap"
                                        >
                                            {{ $tab['season']->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @foreach($seasons as $tab)
                            @php $season = $tab['season']; @endphp
                            <div
                                @if(count($seasons) > 1)
                                    x-show="activeSeason === {{ $season->id }}"
                                    x-cloak
                                    role="tabpanel"
                                @endif
                            >
                                @if(count($seasons) <= 1)
                                    <h2 class="section-title mt-10">{{ $season->name }}</h2>
                                @endif

                                @if($tab['champion'])
                                    <p class="mb-4 text-sm">
                                        Mistrz szczebla:
                                        <span class="font-semibold">{{ $tab['champion']['playerName'] }}</span>
                                    </p>
                                @endif

                                <div class="table-wrap mb-6">
                                    <table class="table-surface">
                                        <thead>
                                        <tr>
                                            <th class="w-12 text-center tracking-normal">#</th>
                                            <th class="text-left">Zawodnik</th>
                                            <th class="w-12 text-center tracking-normal">M</th>
                                            <th class="w-12 text-center tracking-normal text-success-bright">W</th>
                                            @if($tab['allowsDraws'])
                                                <th class="w-12 text-center tracking-normal text-warning">R</th>
                                            @endif
                                            <th class="w-12 text-center tracking-normal text-danger-text">P</th>
                                            @if($tab['allowsDraws'])
                                                <th class="w-12 text-center tracking-normal">Pkt</th>
                                            @endif
                                            <th class="w-14 text-center tracking-normal">+/−</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($tab['standings'] as $row)
                                            <tr>
                                                <td class="score-num text-center">{{ $row->place }}@if($row->needsTiebreak)*@endif</td>
                                                <td>
                                                    {{ $playerName($tab, $row->playerId) }}
                                                    <x-three-dart-average :value="$tab['threeDartAverages']->leaguePlayerAverage((int) $row->playerId)" />
                                                </td>
                                                <td class="score-num text-center">{{ $row->played }}</td>
                                                <td class="score-num text-center text-success-bright">{{ $row->wins }}</td>
                                                @if($tab['allowsDraws'])
                                                    <td class="score-num text-center text-warning">{{ $row->draws }}</td>
                                                @endif
                                                <td class="score-num text-center text-danger-text">{{ $row->losses }}</td>
                                                @if($tab['allowsDraws'])
                                                    <td class="score-num text-center">{{ $row->points }}</td>
                                                @endif
                                                <td class="score-num text-center">{{ $row->unitDiff > 0 ? '+' : '' }}{{ $row->unitDiff }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $tab['allowsDraws'] ? 8 : 6 }}" class="text-text-muted">Brak zawodników na tym szczeblu.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <h3 class="section-title mt-8 mb-3">Osiągnięcia</h3>
                                @if($tab['highlights'] === [])
                                    <p class="text-text-muted text-sm mb-6">Brak zapisanych 180 i checkoutów w tym sezonie.</p>
                                @else
                                    <div class="table-wrap mb-6">
                                        <table class="table-surface">
                                            <thead>
                                            <tr>
                                                <th class="text-left">Zawodnik</th>
                                                <th class="w-16 text-center tracking-normal">180</th>
                                                <th class="w-16 text-center tracking-normal">170+</th>
                                                <th class="w-24 text-center tracking-normal">Najlepszy checkout</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($tab['highlights'] as $highlight)
                                                <tr>
                                                    <td>{{ $highlight['playerName'] }}</td>
                                                    <td class="score-num text-center">{{ $highlight['count180'] }}</td>
                                                    <td class="score-num text-center">{{ $highlight['count170Plus'] }}</td>
                                                    <td class="score-num text-center">{{ $highlight['bestCheckout'] ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                <p class="mb-8">
                                    <a href="{{ route('league-seasons.show', $season) }}" class="text-accent font-semibold hover:underline">Pełny sezon {{ $season->name }}</a>
                                </p>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
