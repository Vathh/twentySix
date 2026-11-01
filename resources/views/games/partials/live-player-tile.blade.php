@php
    $tileTag = $player->user_id ? 'a' : 'div';
@endphp
<{{ $tileTag }}
    @if($player->user_id) href="{{ route('players.show', $player) }}" @endif
    class="match-player"
>
    <x-player-avatar :player="$player" />
    <span class="match-player-name" x-text="{{ $nameBinding }}">{{ $liveName }}</span>
</{{ $tileTag }}>
