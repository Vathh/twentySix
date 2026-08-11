@extends('layouts.app')

@section('title', $tournament ? $tournament->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @if($canManageTournament)
            @include('tournaments.partials.admin-sidebar')
        @endif

        <div class="detail-main">
            <div class="detail-content">

                <header class="entity-header">
                    @if($season)
                        <nav class="entity-breadcrumb" aria-label="Okruszki">
                            <a href="{{ route('leagues.show', $season->league->id) }}">{{ $season->league->name }}</a>
                            <span class="entity-breadcrumb-sep">/</span>
                            <a href="{{ route('seasons.show', $season->id) }}">{{ $season->name }}</a>
                            <span class="entity-breadcrumb-sep">/</span>
                            <span class="text-text-secondary">Turniej</span>
                        </nav>
                    @else
                        <p class="entity-eyebrow">Turniej jednorazowy</p>
                    @endif

                    <div class="entity-title-row">
                        <h1 class="entity-title">{{ $tournament->name }}</h1>
                        @php $variant = $tournament->status->badgeVariant(); @endphp
                        <span @class([
                            'badge-planned' => $variant === 'planned',
                            'badge-status-live' => $variant === 'live',
                            'badge-finished' => $variant === 'finished',
                        ])>
                            {{ $tournament->status->label() }}
                        </span>
                    </div>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <div class="entity-meta">
                    <dl class="entity-meta-grid cols-2">
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Data rozgrywek</dt>
                            <dd class="entity-meta-value score-num">{{ $tournament->getDate() ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                @if($canManageTournament && $tournament->isStarted() && !empty($loginCode))
                    @include('tournaments.partials.login-codes', [
                        'loginCode' => $loginCode,
                        'loginUrl' => $loginUrl,
                        'tournamentId' => $tournament->id,
                    ])
                @endif

                <div class="overflow-x-auto -mx-1 px-1 mb-8 mt-10">
                    <div class="flex border-b border-border min-w-max">
                    @php
                        $isEliminationOnly = $tournament->format->isEliminationOnly();
                        if ($isEliminationOnly && $tab === 'groups') {
                            $tab = 'playoff';
                        }
                        $tabs = $isEliminationOnly
                            ? [
                                'results' => 'Wyniki',
                                'playoff' => 'Drabinka',
                                'achievements' => 'Osiągnięcia',
                            ]
                            : [
                                'results' => 'Wyniki',
                                'playoff' => 'Playoff',
                                'groups' => 'Grupy',
                                'achievements' => 'Osiągnięcia',
                            ];
                    @endphp

                    @foreach($tabs as $key => $label)
                        <a href="{{ route('tournaments.show', [$tournament->id, 'tab' => $key]) }}"
                           class="px-4 sm:px-5 py-3 text-sm font-semibold transition border-b-2 -mb-px whitespace-nowrap
                  {{ $tab === $key
                        ? 'border-accent text-accent'
                        : 'border-transparent text-text-muted hover:text-accent' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                    </div>
                </div>

                @if($tournament->isStarted())
                    @if($tab === 'playoff')
                        @if($tournament->hasPlayoffBracket())
                            @include('tournaments.tabs.playoff', [
                                'playoffGames' => $playoffGames,
                                'bracketHeading' => $isEliminationOnly ? 'Drabinka' : 'Playoff',
                            ])
                        @endif
                    @elseif($tab === 'groups' && ! $isEliminationOnly)
                        @include('tournaments.tabs.groups', ['groupNumbers' => $groupNumbers,
                                                            'players' => $players,
                                                            'games' => $games,
                                                            'groupStandings' => $groupStandings,
                                                            'groupPlayoffHighlights' => $groupPlayoffHighlights])
                    @elseif($tab === 'results')
                        @include('tournaments.tabs.results', [
                            'showPointsColumn' => $tournament->tracksLeaguePoints(),
                            'showStageColumn' => $tournament->format !== \App\Enums\TournamentFormat::DoubleElimination,
                        ])
                    @elseif($tab === 'achievements')
                        @include('tournaments.tabs.achievements', ['achievements' => $achievements])
                    @endif
                @endif
            </div>
        </div>

    </div>

@endsection
