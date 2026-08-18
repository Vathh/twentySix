@extends('layouts.app')

@section('title', $season->name)

@section('content')
    @php
        $isAdmin = auth()->check() && $organization->admins->contains('id', auth()->id());
        $playerName = function (int $id) use ($season) {
            $participant = $season->participants->firstWhere('player_id', $id);
            return $participant?->player?->name ?? ('#'.$id);
        };
    @endphp

    <div x-data="{ cancelOpen: {{ ($errors->has('current_password') || $errors->has('season_name_confirmation')) ? 'true' : 'false' }} }">
    <div class="detail-layout">
        @if($isAdmin)
            <aside class="admin-sidebar">
                <h2 class="admin-sidebar-title">⚙️ Sezon ligowy</h2>
                <nav class="flex flex-col space-y-3">
                    @if($season->status->value === 'draft')
                        <form method="POST" action="{{ route('league-seasons.start', $season) }}">
                            @csrf
                            <button type="submit" class="admin-sidebar-link w-full text-left">▶️ Wystartuj sezon</button>
                        </form>
                    @endif
                    @if($canAdvance)
                        <form method="POST" action="{{ route('league-seasons.advance', $season) }}">
                            @csrf
                            <button type="submit" class="admin-sidebar-link w-full text-left">⏭️ Następny krok (dogrywki / baraże / koniec)</button>
                        </form>
                    @endif
                    <a href="{{ route('leagues.show', $league) }}" class="admin-sidebar-link">← Liga</a>
                    <button type="button" class="admin-sidebar-link w-full text-left text-danger" @click="cancelOpen = true">
                        Anuluj sezon
                    </button>
                </nav>
            </aside>
        @endif

        <div class="detail-main">
            <div class="detail-content">
                <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>

                <header class="entity-header">
                    <p class="entity-eyebrow">Sezon ligowy · {{ $season->status->label() }}</p>
                    <h1 class="entity-title">{{ $season->name }}</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <p class="text-text-muted mb-8">
                    {{ $season->calendar_mode->label() }}
                    @if($season->calendar_mode->value === 'matchdays' && $season->matchday_planning?->value === 'fixed_length' && $season->matchday_length_days)
                        · kolejka: {{ \App\Domain\League\LeagueMatchdayCalendar::lengthLabel((int) $season->matchday_length_days) }}
                    @elseif($season->calendar_mode->value === 'matchdays' && $season->matchday_planning?->value === 'equal_span')
                        · kolejki z równego podziału sezonu
                    @endif
                    · każdy z każdym × {{ $season->rounds_each }}
                    · {{ $season->start_date->format('Y-m-d') }} – {{ $season->end_date->format('Y-m-d') }}
                </p>

                @if($season->matchdays->isNotEmpty())
                    <div class="card mb-8">
                        <h2 class="section-title text-accent mt-0">Kolejki</h2>
                        <p class="text-text-muted text-sm mb-3">Mecze danej kolejki trzeba rozegrać w podanym oknie.</p>
                        <ol class="space-y-1 text-sm">
                            @foreach($season->matchdays as $matchday)
                                <li>
                                    <span class="font-semibold">Kolejka {{ $matchday->round_number }}</span>
                                    <span class="text-text-muted">· {{ $matchday->windowLabel() }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @foreach($divisions as $block)
                    @php $division = $block['division']; @endphp
                    <h2 class="section-title mt-10">{{ $division->name }}</h2>
                    <div class="table-wrap mb-6">
                        <table class="table-surface">
                            <thead>
                            <tr>
                                <th class="w-12 text-center tracking-normal">#</th>
                                <th class="text-left">Zawodnik</th>
                                <th class="w-12 text-center tracking-normal">M</th>
                                <th class="w-12 text-center tracking-normal">W</th>
                                <th class="w-12 text-center tracking-normal">P</th>
                                <th class="w-14 text-center tracking-normal">+/−</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($block['standings'] as $row)
                                <tr>
                                    <td class="score-num text-center">{{ $row->place }}@if($row->needsTiebreak)*@endif</td>
                                    <td>
                                        {{ $playerName($row->playerId) }}
                                        @if($isAdmin && $season->status->isOpen())
                                            <form method="POST" action="{{ route('league-seasons.withdraw', $season) }}" class="inline ml-2"
                                                  onsubmit="return confirm('Rezygnacja anuluje wszystkie mecze tej osoby i wypisuje ją z piramidy. Kontynuować?')">
                                                @csrf
                                                <input type="hidden" name="player_id" value="{{ $row->playerId }}">
                                                <button type="submit" class="text-xs text-danger hover:underline">Rezygnacja</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="score-num text-center">{{ $row->played }}</td>
                                    <td class="score-num text-center">{{ $row->wins }}</td>
                                    <td class="score-num text-center">{{ $row->losses }}</td>
                                    <td class="score-num text-center">{{ $row->unitDiff > 0 ? '+' : '' }}{{ $row->unitDiff }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-text-muted">Brak zawodników na tym szczeblu.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-2 mb-8">
                        @if($season->matchdays->isNotEmpty())
                            @foreach($season->matchdays as $matchday)
                                @php
                                    $roundGames = $block['games']->where('league_season_matchday_id', $matchday->id);
                                    $isCurrentMatchday = now()->betweenIncluded(
                                        $matchday->window_start->copy()->startOfDay(),
                                        $matchday->window_end->copy()->endOfDay()
                                    );
                                @endphp
                                @if($roundGames->isNotEmpty())
                                    <details class="group" @if($isCurrentMatchday) open @endif>
                                        <summary class="cursor-pointer list-none flex items-center justify-between gap-3 text-sm text-text-muted hover:text-text transition mt-4 mb-1 py-1 [&::-webkit-details-marker]:hidden">
                                            <span>Kolejka {{ $matchday->round_number }} · {{ $matchday->windowLabel() }}</span>
                                            <span class="shrink-0 text-xs transition group-open:rotate-180" aria-hidden="true">▼</span>
                                        </summary>
                                        <div class="space-y-2">
                                            @foreach($roundGames as $game)
                                                <a href="{{ route('league-games.show', $game) }}" class="block">
                                                    <div class="list-item">
                                                        {{ $game->player1?->name }}
                                                        <span class="score-num mx-2">
                                                            @if($game->status->value === 'finished')
                                                                {{ $game->player1_score }} : {{ $game->player2_score }}
                                                            @elseif($game->status->value === 'voided')
                                                                anulowany
                                                            @else
                                                                vs
                                                            @endif
                                                        </span>
                                                        {{ $game->player2?->name }}
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @endforeach
                        @else
                            @foreach($block['games'] as $game)
                                <a href="{{ route('league-games.show', $game) }}" class="block">
                                    <div class="list-item">
                                        {{ $game->player1?->name }}
                                        <span class="score-num mx-2">
                                            @if($game->status->value === 'finished')
                                                {{ $game->player1_score }} : {{ $game->player2_score }}
                                            @elseif($game->status->value === 'voided')
                                                anulowany
                                            @else
                                                vs
                                            @endif
                                        </span>
                                        {{ $game->player2?->name }}
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                @endforeach

                @if($tiebreakGames->isNotEmpty())
                    <h2 class="section-title mt-10">Dogrywki</h2>
                    <div class="space-y-2 mb-8">
                        @foreach($tiebreakGames as $game)
                            <a href="{{ route('league-games.show', $game) }}" class="block">
                                <div class="list-item">
                                    {{ $game->player1?->name }}
                                    <span class="score-num mx-2">
                                        {{ $game->status->value === 'finished' ? $game->player1_score.' : '.$game->player2_score : 'vs' }}
                                    </span>
                                    {{ $game->player2?->name }}
                                    @if($game->is_third_place)
                                        <span class="text-text-muted text-sm">· o 3. miejsce</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if($playoffGames->isNotEmpty())
                    <h2 class="section-title mt-10">Baraże</h2>
                    <div class="space-y-2 mb-8">
                        @foreach($playoffGames as $game)
                            <a href="{{ route('league-games.show', $game) }}" class="block">
                                <div class="list-item">
                                    {{ $game->player1?->name }}
                                    <span class="score-num mx-2">
                                        {{ $game->status->value === 'finished' ? $game->player1_score.' : '.$game->player2_score : 'vs' }}
                                    </span>
                                    {{ $game->player2?->name }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

        @if($isAdmin)
            <div
                x-show="cancelOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                @keydown.escape.window="cancelOpen = false"
                @click.self="cancelOpen = false"
            >
                <div class="w-full max-w-md rounded-xl border border-danger/40 bg-bg-deep p-6" @click.stop>
                    <h2 class="text-lg font-semibold text-danger mb-2">Na pewno anulować sezon?</h2>
                    <p class="text-text-muted text-sm mb-4">
                        Znikną mecze, wyniki, kolejki i baraże. Skład piramidy wróci do stanu ze startu.
                        Tej operacji nie da się cofnąć.
                    </p>
                    <form method="POST" action="{{ route('league-seasons.cancel', $season) }}" class="space-y-4">
                        @csrf
                        <label class="block">
                            <span class="form-label">Wpisz nazwę sezonu
                                <span class="text-text-muted font-normal">({{ $season->name }})</span>
                            </span>
                            <input class="input-field" type="text" name="season_name_confirmation" autocomplete="off" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Hasło Twojego konta</span>
                            <input class="input-field" type="password" name="current_password" autocomplete="current-password" required>
                        </label>
                        <x-errors/>
                        <div class="flex flex-col sm:flex-row gap-3 pt-2">
                            <button type="submit" class="btn btn-danger flex-1">Anuluj sezon</button>
                            <button type="button" class="btn btn-secondary flex-1" @click="cancelOpen = false">Powrót</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
