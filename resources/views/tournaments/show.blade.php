@extends('layouts.app')

@section('title', $tournament ? $tournament->name : 'Szczegóły')

@section('content')

    <div class="flex min-h-screen bg-dark-bg text-light-white">

        @seasonAdmin($season)
        <aside class="w-72 backdrop-blur bg-white/5 border-r border-white/10 p-6 flex flex-col">
            <h2 class="text-light-green font-bold text-lg mb-6 tracking-wide">⚙️ Zarządzanie turniejem</h2>

            @if(!$tournament->isStarted())
                <nav class="flex flex-col space-y-3">
                    <a href="{{ route('tournaments.start', $tournament->id) }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        ➕ Rozpocznij turniej
                    </a>
                </nav>
            @endif
        </aside>
        @endseasonAdmin

        <div class="flex-1 p-10 flex justify-center">
            <div class="max-w-3xl w-full">

                <h2 class="text-4xl font-bold text-light-green mb-6 tracking-wide hover:text-light-orange transition-all duration-300 hover:cursor-pointer">
                    <a href="{{ route('leagues.show', $season->league->id) }}">{{ $season->league->name }}</a>
                </h2>

                <h1 class="text-3xl font-bold text-light-green mb-6 tracking-wide hover:text-light-orange transition-all duration-300 hover:cursor-pointer">
                    <a href="{{ route('seasons.show', $season->id) }}">{{ $season->name }}</a>
                </h1>

                <h1 class="text-2xl font-bold text-light-orange mb-6 tracking-wide">{{ $tournament->name }}</h1>

                <div class="bg-white/5 border border-white/10 p-6 rounded-xl shadow-lg backdrop-blur">
                    <p class="mb-2"><span
                            class="text-light-green font-semibold">Data rozgrywek:</span> {{ $tournament->getDate() }}
                    </p>
                </div>

                <div class="flex border-b border-white/10 mb-8">
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
                                                            'groupStandings' => $groupStandings])
                    @elseif($tab === 'results')
                        @include('tournaments.tabs.results')
                    @elseif($tab === 'achievements')
                        @include('tournaments.tabs.achievements', ['achievements' => $achievements])
                    @endif

                @endif
            </div>
        </div>

    </div>

@endsection

