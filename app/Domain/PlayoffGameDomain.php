<?php

namespace App\Domain;

use App\Enums\GameStatus;
use App\Enums\PlayoffRound;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
use App\Models\PlayoffGame;

class PlayoffGameDomain
{

    public function __construct(
        public readonly ?int $id,
        public readonly ?int $tournamentId,
        public readonly ?TournamentDomain $tournament,
        public readonly PlayoffRound $round,
        public readonly PlayoffSlot $slot,
        public readonly ?int $player1Id,
        public readonly ?int $player2Id,
        public readonly ?PlayerDomain $player1,
        public readonly ?PlayerDomain $player2,
        public readonly ?int $player1Score,
        public readonly ?int $player2Score,
        public readonly ?int $winnerId,
        public readonly ?PlayerDomain $winner,
        public readonly ?WinnerDestinationSlot $winnerDestinationSlot,
        public readonly ?GameStatus $status
    )
    {
    }

    /**
     * @param PlayoffGame $game
     * @param array $with
     * @return PlayoffGameDomain
     */
    public static function fromEloquent(PlayoffGame $game, array $with = []): PlayoffGameDomain
    {
        $game->loadMissing(array_intersect($with, ['tournament', 'player1', 'player2', 'winner']));

        return new self(
            id: $game->id,
            tournamentId: $game->tournament_id,
            tournament: in_array('tournament', $with)
                ? TournamentDomain::fromEloquent($game->tournament)
                : null,
            round: $game->round,
            slot: $game->slot,
            player1Id: $game->player1_id,
            player2Id: $game->player2_id,
            player1: in_array('player1', $with)
                ? PlayerDomain::fromEloquent($game->player1)
                : null,
            player2: in_array('player2', $with)
                ? PlayerDomain::fromEloquent($game->player2)
                : null,
            player1Score: $game->player1Score,
            player2Score: $game->player2Score,
            winnerId: $game->winner_id,
            winner: in_array('winner', $with)
                ? PlayerDomain::fromEloquent($game->winner)
                : null,
            winnerDestinationSlot: $game->winnerDestinationSlot,
            status: $game->status
        );
    }

    /**
     * @param int $player1Id
     * @param int $player2Id
     * @return PlayoffGameDomain
     */
    public function withPlayerIds(int $player1Id, int $player2Id): PlayoffGameDomain
    {
        return new self(
            id: $this->id,
            tournamentId: $this->tournamentId,
            tournament: $this->tournament,
            round: $this->round,
            slot: $this->slot,
            player1Id: $player1Id,
            player2Id: $player2Id,
            player1: $this->player1,
            player2: $this->player2,
            player1Score: $this->player1Score,
            player2Score: $this->player2Score,
            winnerId: $this->winnerId,
            winner: $this->winner,
            winnerDestinationSlot: $this->winnerDestinationSlot,
            status: $this->status
        );
    }
}
