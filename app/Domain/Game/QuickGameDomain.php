<?php

namespace App\Domain\Game;

use App\Domain\PlayerDomain;
use App\Enums\GameStatus;
use App\Models\QuickGame;

class QuickGameDomain extends GameDomain
{
    /**
     * @param int|null $id
     * @param PlayerDomain|null $player1
     * @param PlayerDomain|null $player2
     * @param int|null $player1Score
     * @param int|null $player2Score
     * @param PlayerDomain|null $winner
     * @param GameStatus $status
     */
    public function __construct(
        ?int $id,
        ?PlayerDomain $player1,
        ?PlayerDomain $player2,
        ?int $player1Score,
        ?int $player2Score,
        ?PlayerDomain $winner,
        GameStatus $status
    )
    {
        parent::__construct(
            id: $id,
            player1: $player1,
            player2: $player2,
            player1Score: $player1Score ?? 0,
            player2Score: $player2Score ?? 0,
            winner: $winner,
            status: $status
        );
    }

    /**
     * @param QuickGame $quickGame
     * @param array $with
     * @return QuickGameDomain
     */
    public static function fromEloquent(QuickGame $quickGame, array $with = []): QuickGameDomain
    {
        $quickGame->loadMissing(array_intersect($with, ['player1', 'player2', 'winner']));

        $player1 = in_array('player1', $with) && $quickGame->player1
            ? PlayerDomain::fromEloquent($quickGame->player1)
            : null;
        $player2 = in_array('player2', $with) && $quickGame->player2
            ? PlayerDomain::fromEloquent($quickGame->player2)
            : null;
        $winner = in_array('winner', $with) && $quickGame->winner
            ? PlayerDomain::fromEloquent($quickGame->winner)
            : null;

        return new self(
            id: $quickGame->id,
            player1: $player1,
            player2: $player2,
            player1Score: $quickGame->player1_score,
            player2Score: $quickGame->player2_score,
            winner: $winner,
            status: $quickGame->status
        );
    }

    public function checkUpdateDataAccuracy(int $player1Id, int $player2Id, int $winnerId): void
    {
        $this->validatePlayers($player1Id, $player2Id);
        $this->validateWinner($winnerId);
        $this->validateNotFinished();
    }
}
