@php
    $finished = $game->status->value === 'finished';
    $voided = $game->status->value === 'voided';
    $player1Average = $threeDartAverages->leagueMatchAverage((int) $game->id, (int) $game->player1_id);
    $player2Average = $threeDartAverages->leagueMatchAverage((int) $game->id, (int) $game->player2_id);
@endphp
<a href="{{ route('league-games.show', $game) }}" class="block">
    <div class="list-item flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center">
        <span>
            {{ $game->player1?->name }}
            <x-three-dart-average :value="$player1Average" />
        </span>
        <span class="score-num">
            @if($finished)
                {{ $game->player1_score }} : {{ $game->player2_score }}
            @elseif($voided)
                anulowany
            @else
                vs
            @endif
        </span>
        <span>
            {{ $game->player2?->name }}
            <x-three-dart-average :value="$player2Average" />
        </span>
        @if(! empty($hint))
            <span class="text-text-muted text-sm">{{ $hint }}</span>
        @endif
    </div>
</a>
