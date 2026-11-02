<span class="ticker-item" data-key="{{ $item['key'] }}">
    <span class="ticker-label" data-field="label">{{ $item['label'] }}</span>
    <span class="ticker-result" data-field="result" @if(empty($item['finished'])) hidden @endif>koniec</span>
    <span class="ticker-name" data-field="player1">{{ $item['player1'] }}</span>
    <span class="ticker-score">
        <span data-field="score1">{{ $item['score1'] }}</span>
        <span class="ticker-dash" aria-hidden="true">–</span>
        <span data-field="score2">{{ $item['score2'] }}</span>
    </span>
    <span class="ticker-legs" data-field="legs" @if($item['setLegs1'] === null) hidden @endif>@if($item['setLegs1'] !== null)({{ $item['setLegs1'] }}–{{ $item['setLegs2'] }})@endif</span>
    <span class="ticker-name" data-field="player2">{{ $item['player2'] }}</span>
</span>
