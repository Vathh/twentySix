<?php

namespace App\Domain;

use App\Enums\GameStatus;
use App\Models\Game;

class GameDomain
{

    public function __construct(
        public readonly int $id,
        public readonly TournamentDomain $tournament,
        public readonly PlayerDomain $player1,
        public readonly PlayerDomain $player2,
        public readonly int $player1Score,
        public readonly int $player2Score,
        public readonly PlayerDomain $winner,
        public readonly int $groupNumber,
        public readonly GameStatus $status
    )
    {
    }

    public static function fromEloquent(Game $game, array $with = []): GameDomain
    {
        $game->loadMissing(array_intersect($with, ['tournament', 'player1', 'player2', 'winner']));

        return new self(
            id: $game->id,
            tournament: in_array('tournament', $with)
                ? TournamentDomain::fromEloquent($game->tournament)
                : null,
            player1: in_array('player1', $with)
                ? PlayerDomain::fromEloquent($game->player1)
                : null,
            player2: in_array('player2', $with)
                ? PlayerDomain::fromEloquent($game->player2)
                : null,
            player1Score: $game->player1Score,
            player2Score: $game->player2Score,
            winner: in_array('winner', $with)
                ? PlayerDomain::fromEloquent($game->winner)
                : null,
            groupNumber: $game->groupNumber,
            status: $game->status
        );
    }
}
