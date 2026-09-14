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

    $byeVsBye = $game->player1?->isBye === true && $game->player2?->isBye === true;
    $showLegScores = ! $byeVsBye && ($game->isFinished() || $game->status === \App\Enums\GameStatus::IN_PROGRESS);
    $formatLegScore = static function (?int $score) use ($showLegScores): string {
        if ($showLegScores) {
            return (string) (int) ($score ?? 0);
        }

        return $score !== null ? (string) $score : '';
    };
@endphp
<div class="w-full" @if($game->id) data-playoff-game-id="{{ $game->id }}" @endif>
    <a
        data-playoff-game-card
        @if($gameUrl)
            href="{{ $gameUrl }}"
            class="block card-glass p-3 hover:border-success/50 transition cursor-pointer"
        @else
            href="#"
            class="block card-glass p-3"
            aria-disabled="true"
            tabindex="-1"
            style="pointer-events: none"
        @endif
    >
        <div
            data-playoff-p1-row
            class="flex justify-between items-center mb-1 {{ ! $byeVsBye && $game->winnerId === $game->player1Id ? 'text-accent font-semibold' : '' }}"
        >
            <span class="truncate" data-playoff-p1-name>
                {{ $game->player1?->name ?? '—' }}
            </span>
            <span class="ml-2" data-playoff-p1-score>
                {{ $formatLegScore($game->player1Score) }}
            </span>
        </div>

        <div
            data-playoff-p2-row
            class="flex justify-between items-center {{ ! $byeVsBye && $game->winnerId === $game->player2Id ? 'text-accent font-semibold' : '' }}"
        >
            <span class="truncate" data-playoff-p2-name>
                {{ $game->player2?->name ?? '—' }}
            </span>
            <span class="ml-2" data-playoff-p2-score>
                {{ $formatLegScore($game->player2Score) }}
            </span>
        </div>
    </a>
</div>
