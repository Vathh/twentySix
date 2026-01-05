<?php

namespace App\Domain;

use App\Enums\GameStatus;
use App\Models\Game;

class GameDomain
{

    public function __construct(
        public readonly int $id,
        public readonly ?TournamentDomain $tournament,
        public readonly ?PlayerDomain $player1,
        public readonly ?PlayerDomain $player2,
        public readonly int $player1Score,
        public readonly int $player2Score,
        public readonly ?PlayerDomain $winner,
        public readonly int $groupNumber,
        public readonly GameStatus $status
    )
    {
    }

    /**
     * @param Game $game
     * @param array $with
     * @return GameDomain
     */
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
            player1Score: $game->player1_score,
            player2Score: $game->player2_score,
            winner: in_array('winner', $with) && $game->winner
                ? PlayerDomain::fromEloquent($game->winner)
                : null,
            groupNumber: $game->group_number,
            status: $game->status
        );
    }

    /**
     * @return array
     */
    public function playerIds(): array
    {
        return [$this->player1->id, $this->player2->id];
    }
}
