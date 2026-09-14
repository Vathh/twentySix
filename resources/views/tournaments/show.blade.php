@extends('layouts.app')

@section('title', $tournament ? $tournament->name : 'Szczegóły')

@section('content')

    <div x-data="{ cancelOpen: {{ $errors->has('current_password') ? 'true' : 'false' }} }">
    <div class="detail-layout">

        @if($canManageTournament)
            @include('tournaments.partials.admin-sidebar')
        @endif

        <div class="detail-main">
            <div class="detail-content">

                <header class="entity-header">
                    @if($season)
                        <nav class="entity-breadcrumb" aria-label="Okruszki">
                            <a href="{{ route('organizations.show', $season->organization->id) }}">{{ $season->organization->name }}</a>
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

                @if(! $tournament->isStarted())
                    <p class="text-center text-text-muted mt-10">
                        Turniej jeszcze się nie rozpoczął. Wyniki i tabele pojawią się po starcie.
                    </p>
                @else
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
                            'showPointsColumn' => $tournament->tracksSeasonPoints(),
                            'showStageColumn' => $tournament->format !== \App\Enums\TournamentFormat::DoubleElimination,
                        ])
                    @elseif($tab === 'achievements')
                        @include('tournaments.tabs.achievements', ['achievements' => $achievements])
                    @endif
                @endif
            </div>
        </div>

    </div>

    @if($canManageTournament && $tournament->canCancelPlay())
        <div
            x-show="cancelOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @keydown.escape.window="cancelOpen = false"
            @click.self="cancelOpen = false"
        >
            <div class="w-full max-w-md rounded-xl border border-danger/40 bg-bg-deep p-6" @click.stop>
                <h2 class="text-lg font-semibold text-danger mb-2">Na pewno anulować rozgrywki?</h2>
                <p class="text-text-muted text-sm mb-4">
                    Znikną mecze, drabinka, tabele i wyniki. Zaproszenia i goście zostaną.
                    Tej operacji nie da się cofnąć.
                </p>
                <form method="POST" action="{{ route('tournaments.cancel', $tournament->id) }}" class="space-y-4">
                    @csrf
                    <label class="block">
                        <span class="form-label">Hasło Twojego konta</span>
                        <input class="input-field" type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <x-errors/>
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit" class="btn btn-danger flex-1">Anuluj rozgrywki</button>
                        <button type="button" class="btn btn-secondary flex-1" @click="cancelOpen = false">Powrót</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    </div>

@endsection
