@php
    $gameUrl = null;
    if ($game->id) {
        if ($game->isFinished()) {
            $gameUrl = route('games.show', ['type' => 'playoff', 'id' => $game->id]);
        } elseif ($game->status === \App\Enums\GameStatus::IN_PROGRESS) {
            $gameUrl = route('games.live', ['type' => 'playoff', 'id' => $game->id]);
        }
    }
@endphp
@if($gameUrl)
<a href="{{ $gameUrl }}" class="block bg-white/5 border border-white/10 rounded-xl p-3 backdrop-blur shadow-sm hover:border-light-green/50 transition cursor-pointer">
@else
<div class="bg-white/5 border border-white/10 rounded-xl p-3 backdrop-blur shadow-sm">
@endif

    <div class="flex justify-between items-center mb-1
        {{ $game->winnerId === $game->player1Id ? 'text-light-green font-semibold' : '' }}">
        <span class="truncate">
            {{ $game->player1?->name ?? '—' }}
        </span>
        <span class="ml-2">
            {{ $game->player1Score ?? '' }}
        </span>
    </div>

    <div class="flex justify-between items-center
        {{ $game->winnerId === $game->player2Id ? 'text-light-green font-semibold' : '' }}">
        <span class="truncate">
            {{ $game->player2?->name ?? '—' }}
        </span>
        <span class="ml-2">
            {{ $game->player2Score ?? '' }}
        </span>
    </div>

@if($gameUrl)
</a>
@else
</div>
@endif
