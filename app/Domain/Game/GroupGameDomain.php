<?php

namespace App\Domain\Game;

use App\Domain\PlayerDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStatus;
use App\Models\Game;

class GroupGameDomain extends GameDomain
{
    /**
     * @param int $id
     * @param TournamentDomain|null $tournament
     * @param PlayerDomain|null $player1
     * @param PlayerDomain|null $player2
     * @param int $player1Score
     * @param int $player2Score
     * @param PlayerDomain|null $winner
     * @param int $groupNumber
     * @param GameStatus $status
     */
    public function __construct(
        int $id,
        public readonly ?TournamentDomain $tournament,
        ?PlayerDomain $player1,
        ?PlayerDomain $player2,
        int $player1Score,
        int $player2Score,
        ?PlayerDomain $winner,
        public readonly int $groupNumber,
        GameStatus $status
    )
    {
        parent::__construct(
            id: $id,
            player1: $player1,
            player2: $player2,
            player1Score: $player1Score,
            player2Score: $player2Score,
            winner: $winner,
            status: $status
        );
    }

    /**
     * @param Game $game
     * @param array $with
     * @return GroupGameDomain
     */
    public static function fromEloquent(Game $game, array $with = []): GroupGameDomain
    {
        $game->loadMissing(array_intersect($with, ['tournament', 'player1', 'player2', 'winner']));

        $player1 = in_array('player1', $with) && $game->player1
            ? PlayerDomain::fromEloquent($game->player1)
            : null;
        $player2 = in_array('player2', $with) && $game->player2
            ? PlayerDomain::fromEloquent($game->player2)
            : null;
        $winner = in_array('winner', $with) && $game->winner
            ? PlayerDomain::fromEloquent($game->winner)
            : null;

        return new self(
            id: $game->id,
            tournament: in_array('tournament', $with) && $game->tournament
                ? TournamentDomain::fromEloquent($game->tournament)
                : null,
            player1: $player1,
            player2: $player2,
            player1Score: $game->player1_score ?? 0,
            player2Score: $game->player2_score ?? 0,
            winner: $winner,
            groupNumber: $game->group_number,
            status: $game->status
        );
    }

    public function checkUpdateDataAccuracy(int $player1Id, int $player2Id, int $winnerId): void
    {
        $this->validatePlayers($player1Id, $player2Id);
        $this->validateWinner($winnerId);
        $this->validateNotFinished();
    }
}
