@extends('layouts.board')

@section('title', $tournament->name)

@section('content')
    @php
        $phaseLabel = match ($tournament->status) {
            \App\Enums\TournamentStatus::CREATED => 'Zaplanowany',
            \App\Enums\TournamentStatus::GROUP => 'Faza grupowa',
            \App\Enums\TournamentStatus::PLAYOFF => $tournament->format->isEliminationOnly() ? 'Drabinka' : 'Playoff',
            \App\Enums\TournamentStatus::FINISHED => 'Zakończony',
        };
        $liveConfig = [
            'channel' => 'tournament.'.$tournament->id,
            'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
        ];
        if ($mode === 'groups') {
            $liveConfig['snapshotUrl'] = route('tournaments.groups-live', $tournament->id);
            $liveConfig['urls'] = [
                'showBase' => url('/games/group'),
                'liveBase' => url('/games/group'),
            ];
        }
        $bracketHeading = $tournament->format->isEliminationOnly() ? 'Drabinka' : 'Playoff';
    @endphp

    <div
        class="board-shell"
        data-board-mode="{{ $mode }}"
        x-data="tournamentBoard(@js([
            'status' => $tournament->status->value,
            'statusUrl' => $statusUrl,
            'channel' => 'tournament.'.$tournament->id,
            'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
        ]))"
    >
        @if($mode === 'groups')
            <div class="board-fill" x-data="tournamentGroupsLive(@js($liveConfig))">
                @include('tournaments.partials.board-header', ['showConnection' => true])
                <div class="board-stage" data-board-stage>
                    <div class="board-scale is-groups" data-board-scale>
                        @include('tournaments.partials.board-groups')
                    </div>
                </div>
            </div>
        @elseif($mode === 'bracket')
            <div
                class="board-fill"
                @if($tournament->isLiveOnWeb())
                    x-data="tournamentPlayoffLive(@js([
                        'channel' => 'tournament.'.$tournament->id,
                        'snapshotUrl' => route('tournaments.playoff-live', $tournament->id),
                        'urls' => [
                            'showBase' => url('/games/playoff'),
                            'liveBase' => url('/games/playoff'),
                        ],
                        'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
                    ]))"
                @endif
            >
                @include('tournaments.partials.board-header', ['showConnection' => $tournament->isLiveOnWeb()])
                <div class="board-stage" data-board-stage>
                    <div class="board-scale is-bracket" data-board-scale>
                        @include('tournaments.tabs.playoff', [
                            'playoffGames' => $playoffGames,
                            'consolationPlayoffGames' => $consolationPlayoffGames,
                            'bracketHeading' => $bracketHeading,
                            'ownsPlayoffLive' => false,
                        ])
                    </div>
                </div>
            </div>
        @else
            <div class="board-fill">
                @include('tournaments.partials.board-header', ['showConnection' => false])
                <div class="board-stage" data-board-stage>
                    <div class="board-scale is-waiting" data-board-scale>
                        <p class="board-waiting">
                            Turniej jeszcze się nie rozpoczął.<br>
                            Tablica włączy się sama po starcie.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
