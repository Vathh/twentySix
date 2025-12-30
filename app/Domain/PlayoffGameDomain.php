<?php

namespace App\Domain;

use App\Enums\GameStatus;
use App\Enums\PlayoffRound;
use App\Models\PlayoffGame;

class PlayoffGameDomain
{

    public function __construct(
        public readonly ?int $id,
        public readonly ?int $tournamentId,
        public readonly PlayoffRound $round,
        public readonly string $slot,
        public readonly PlayerDomain $player1,
        public readonly PlayerDomain $player2,
        public readonly int $player1Score,
        public readonly int $player2Score,
        public readonly PlayerDomain $winner,
        public readonly string $winnerDestinationSlot,
        public readonly GameStatus $status
    )
    {
    }

    public static function fromEloquent(PlayoffGame $game, array $with = []): PlayoffGameDomain
    {
        $game->loadMissing(array_intersect($with, ['tournament', 'player1', 'player2', 'winner']));

        return new self(
            id: $game->id,
            tournament: in_array('tournament', $with)
                ? TournamentDomain::fromEloquent($game->tournament)
                : null,
            round: $game->round,
            slot: $game->slot,
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
            winnerDestinationSlot: $game->winnerDestinationSlot,
            status: $game->status
        );
    }
}
