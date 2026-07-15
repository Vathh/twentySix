<?php

namespace App\Support\GameScoring;

use App\Enums\GameKind;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use DomainException;

readonly class GameScoringContext
{
    public function __construct(
        public GameKind $kind,
        public int $gameId,
        public int $player1Id,
        public int $player2Id,
        public ?int $tournamentId,
        public MatchFormat $matchFormat,
    ) {
    }

    public static function fromGroupGame(Game $game): self
    {
        return new self(
            kind: GameKind::GROUP,
            gameId: $game->id,
            player1Id: (int) $game->player1_id,
            player2Id: (int) $game->player2_id,
            tournamentId: (int) $game->tournament_id,
            matchFormat: MatchFormat::fromRecord($game),
        );
    }

    public static function fromPlayoffGame(PlayoffGame $game): self
    {
        if (! $game->player1_id || ! $game->player2_id) {
            throw new DomainException('Mecz playoff nie ma przypisanych graczy.');
        }

        return new self(
            kind: GameKind::PLAYOFF,
            gameId: $game->id,
            player1Id: (int) $game->player1_id,
            player2Id: (int) $game->player2_id,
            tournamentId: (int) $game->tournament_id,
            matchFormat: MatchFormat::fromRecord($game),
        );
    }

    /**
     * Kontekst szczegółów meczu towarzyskiego na WWW (game_visits powiązane z quick_game_id).
     * Nie używać w flow FFA lobby — quick online kończy się przez QuickGameFfaScoringService.
     */
    public static function fromQuickGame(QuickGame $game): self
    {
        return new self(
            kind: GameKind::QUICK,
            gameId: $game->id,
            player1Id: (int) $game->player1_id,
            player2Id: (int) $game->player2_id,
            tournamentId: null,
            matchFormat: MatchFormat::fromRecord($game),
        );
    }

    public function startingScore(): int
    {
        return $this->matchFormat->startingScore;
    }

    public function broadcastChannelName(): string
    {
        return match ($this->kind) {
            GameKind::GROUP => 'group-game.'.$this->gameId,
            GameKind::PLAYOFF => 'playoff-game.'.$this->gameId,
            GameKind::QUICK => 'quick-game.'.$this->gameId,
        };
    }

    public function otherPlayerId(int $playerId): int
    {
        return $playerId === $this->player1Id ? $this->player2Id : $this->player1Id;
    }
}
