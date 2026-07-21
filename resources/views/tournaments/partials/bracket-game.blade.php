@php
    $gameUrl = null;
    if ($game->id) {
        if ($game->isFinished()) {
            $gameUrl = route('games.show', ['type' => 'playoff', 'id' => $game->id]);
        } elseif ($game->status === \App\Enums\GameStatus::IN_PROGRESS) {
            $gameUrl = route('games.live', ['type' => 'playoff', 'id' => $game->id]);
        } elseif ($game->status === \App\Enums\GameStatus::SCHEDULED && $game->player1 !== null && $game->player2 !== null) {
            $gameUrl = route('games.show', ['type' => 'playoff', 'id' => $game->id]);
        }
    }

    $showLegScores = $game->isFinished() || $game->status === \App\Enums\GameStatus::IN_PROGRESS;
    $formatLegScore = static function (?int $score) use ($showLegScores): string {
        if ($showLegScores) {
            return (string) (int) ($score ?? 0);
        }

        return $score !== null ? (string) $score : '';
    };
@endphp
@if($gameUrl)
<a href="{{ $gameUrl }}" class="block card-glass p-3 hover:border-success/50 transition cursor-pointer">
@else
<div class="card-glass p-3">
@endif

    <div class="flex justify-between items-center mb-1
        {{ $game->winnerId === $game->player1Id ? 'text-accent font-semibold' : '' }}">
        <span class="truncate">
            {{ $game->player1?->name ?? '—' }}
        </span>
        <span class="ml-2">
            {{ $formatLegScore($game->player1Score) }}
        </span>
    </div>

    <div class="flex justify-between items-center
        {{ $game->winnerId === $game->player2Id ? 'text-accent font-semibold' : '' }}">
        <span class="truncate">
            {{ $game->player2?->name ?? '—' }}
        </span>
        <span class="ml-2">
            {{ $formatLegScore($game->player2Score) }}
        </span>
    </div>

@if($gameUrl)
</a>
@else
</div>
@endif
