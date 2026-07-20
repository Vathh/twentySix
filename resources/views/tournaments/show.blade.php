@extends('layouts.app')

@section('title', $tournament ? $tournament->name : 'Szczegóły')

@section('content')

    <div class="flex min-h-screen bg-dark-bg text-light-white">

        @if($canManageTournament)
            @include('tournaments.partials.admin-sidebar')
        @endif

        <div class="flex-1 p-10 flex justify-center">
            <div class="max-w-3xl w-full">

                @if($season)
                    <h2 class="text-4xl font-bold text-light-green mb-6 tracking-wide hover:text-light-orange transition-all duration-300 hover:cursor-pointer">
                        <a href="{{ route('leagues.show', $season->league->id) }}">{{ $season->league->name }}</a>
                    </h2>

                    <h1 class="text-3xl font-bold text-light-green mb-6 tracking-wide hover:text-light-orange transition-all duration-300 hover:cursor-pointer">
                        <a href="{{ route('seasons.show', $season->id) }}">{{ $season->name }}</a>
                    </h1>
                @else
                    <p class="text-sm text-light-orange mb-4">Turniej jednorazowy</p>
                @endif

                <h1 class="text-2xl font-bold text-light-orange mb-6 tracking-wide">{{ $tournament->name }}</h1>

                <div class="bg-white/5 border border-white/10 p-6 rounded-xl shadow-lg backdrop-blur">
                    <p class="mb-2"><span
                            class="text-light-green font-semibold">Data rozgrywek:</span> {{ $tournament->getDate() }}
                    </p>
                </div>

                @if(session('success'))
                    <div class="mt-4 p-3 bg-green-900/50 border border-green-600 rounded text-light-green">{{ session('success') }}</div>
                @endif

                @if($canManageTournament && $tournament->isStarted() && $loginCodes->isNotEmpty())
                    @include('tournaments.partials.login-codes', ['loginCodes' => $loginCodes])
                @endif

                <div class="flex border-b border-white/10 mb-8 mt-8">
                    @php
                        $tabs = [
                            'results' => 'Wyniki',
                            'playoff' => 'Playoff',
                            'groups' => 'Grupy',
                            'achievements' => 'Osiągnięcia',
                        ];
                    @endphp

                    @foreach($tabs as $key => $label)
                        <a href="{{ route('tournaments.show', [$tournament->id, 'tab' => $key]) }}"
                           class="px-5 py-3 text-sm font-semibold transition
                  {{ $tab === $key
                        ? 'border-b-2 border-light-green text-light-green'
                        : 'text-text-muted hover:text-light-green' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @if($tournament->isStarted())
                    @if($tab === 'playoff')
                        @if($tournament->hasPlayoffBracket())
                            @include('tournaments.tabs.playoff', ['playoffGames' => $playoffGames])
                        @endif
                    @elseif($tab === 'groups')
                        @include('tournaments.tabs.groups', ['groupNumbers' => $groupNumbers,
                                                            'players' => $players,
                                                            'games' => $games,
                                                            'groupStandings' => $groupStandings,
                                                            'groupPlayoffHighlights' => $groupPlayoffHighlights])
                    @elseif($tab === 'results')
                        @include('tournaments.tabs.results', [
                            'showPointsColumn' => $tournament->tracksLeaguePoints(),
                        ])
                    @elseif($tab === 'achievements')
                        @include('tournaments.tabs.achievements', ['achievements' => $achievements])
                    @endif

                @endif
            </div>
        </div>

    </div>

@endsection

