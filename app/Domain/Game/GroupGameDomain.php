<?php

namespace App\Domain\Game;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\PlayerDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStatus;
use App\Models\Game\Game;

class GroupGameDomain extends GameDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['tournament', 'player1', 'player2', 'winner', 'referee'];

    public function __construct(
        int $id,
        public readonly ?TournamentDomain $tournament,
        ?PlayerDomain $player1,
        ?PlayerDomain $player2,
        int $player1Score,
        int $player2Score,
        ?PlayerDomain $winner,
        public readonly int $groupNumber,
        GameStatus $status,
        public readonly ?int $sequence = null,
        public readonly ?PlayerDomain $referee = null,
    ) {
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

    public static function fromEloquent(Game $game, array $with = []): GroupGameDomain
    {
        self::assertRelationsLoaded($game, $with, self::RELATIONS);

        $player1 = in_array('player1', $with) && $game->player1
            ? PlayerDomain::fromEloquent($game->player1)
            : null;
        $player2 = in_array('player2', $with) && $game->player2
            ? PlayerDomain::fromEloquent($game->player2)
            : null;
        $winner = in_array('winner', $with) && $game->winner
            ? PlayerDomain::fromEloquent($game->winner)
            : null;
        $referee = in_array('referee', $with, true) && $game->referee
            ? PlayerDomain::fromEloquent($game->referee)
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
            status: $game->status,
            sequence: $game->sequence !== null ? (int) $game->sequence : null,
            referee: $referee,
        );
    }

    public function checkUpdateDataAccuracy(int $player1Id, int $player2Id, int $winnerId): void
    {
        $this->validatePlayers($player1Id, $player2Id);
        $this->validateWinner($winnerId);
        $this->validateNotFinished();
    }
}
