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
            class="bracket-game-card is-link"
        @else
            href="#"
            class="bracket-game-card"
            aria-disabled="true"
            tabindex="-1"
            style="pointer-events: none"
        @endif
    >
        <div
            data-playoff-p1-row
            class="bracket-game-row {{ ! $byeVsBye && $game->winnerId === $game->player1Id ? 'text-accent font-semibold' : '' }}"
        >
            <span class="bracket-player-line">
                <span class="bracket-player-name" data-playoff-p1-name>
                    {{ $game->player1?->name ?? '—' }}
                </span>
                @php
                    $player1Average = $game->player1Id
                        ? $threeDartAverages->playoffMatchAverage((int) $game->id, (int) $game->player1Id)
                        : null;
                @endphp
                <span
                    class="three-dart-average"
                    data-playoff-p1-average
                    @if($player1Average === null) hidden @endif
                >{{ $player1Average !== null ? \App\Support\AverageFormat::display($player1Average) : '' }}</span>
            </span>
            <span class="ml-1 tabular-nums shrink-0" data-playoff-p1-score>
                {{ $formatLegScore($game->player1Score) }}
            </span>
        </div>

        <div
            data-playoff-p2-row
            class="bracket-game-row {{ ! $byeVsBye && $game->winnerId === $game->player2Id ? 'text-accent font-semibold' : '' }}"
        >
            <span class="bracket-player-line">
                <span class="bracket-player-name" data-playoff-p2-name>
                    {{ $game->player2?->name ?? '—' }}
                </span>
                @php
                    $player2Average = $game->player2Id
                        ? $threeDartAverages->playoffMatchAverage((int) $game->id, (int) $game->player2Id)
                        : null;
                @endphp
                <span
                    class="three-dart-average"
                    data-playoff-p2-average
                    @if($player2Average === null) hidden @endif
                >{{ $player2Average !== null ? \App\Support\AverageFormat::display($player2Average) : '' }}</span>
            </span>
            <span class="ml-1 tabular-nums shrink-0" data-playoff-p2-score>
                {{ $formatLegScore($game->player2Score) }}
            </span>
        </div>
    </a>
</div>
