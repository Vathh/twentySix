@php
    $seRounds = $buildRounds($seRoundOrder, $gamesByRound);
    $seThird = null;
    $seMain = [];
    foreach ($seRounds as $round) {
        if ($round['key'] === 'THIRD') {
            $seThird = $round;
            continue;
        }
        $seMain[] = $round;
    }
@endphp

@include('tournaments.partials.bracket-tree', ['rounds' => $seMain])

@if($seThird !== null)
    <div class="bracket-third">
        <p class="bracket-third-label">{{ $seThird['label'] }}</p>
        @foreach($seThird['games'] as $game)
            <div class="bracket-slot-body">
                @include('tournaments.partials.bracket-game', ['game' => $game])
            </div>
        @endforeach
    </div>
@endif
