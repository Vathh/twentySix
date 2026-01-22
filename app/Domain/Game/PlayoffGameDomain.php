<?php

namespace App\Domain\Game;

use App\Domain\PlayerDomain;
use App\Domain\Tournament\TournamentDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Enums\GameStage;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
use App\Models\PlayoffGame;
use DomainException;

class PlayoffGameDomain
{

    /**
     * @param int|null $id
     * @param int|null $tournamentId
     * @param TournamentDomain|null $tournament
     * @param GameStage $round
     * @param PlayoffSlot $slot
     * @param int|null $player1Id
     * @param int|null $player2Id
     * @param PlayerDomain|null $player1
     * @param PlayerDomain|null $player2
     * @param int|null $player1Score
     * @param int|null $player2Score
     * @param int|null $winnerId
     * @param PlayerDomain|null $winner
     * @param WinnerDestinationSlot|null $winnerDestinationSlot
     * @param GameStatus|null $status
     */
    public function __construct(
        public readonly ?int                   $id,
        public readonly ?int                   $tournamentId,
        public readonly ?TournamentDomain      $tournament,
        public readonly GameStage              $round,
        public readonly PlayoffSlot            $slot,
        public readonly ?int                   $player1Id,
        public readonly ?int                   $player2Id,
        public readonly ?PlayerDomain          $player1,
        public readonly ?PlayerDomain          $player2,
        public readonly ?int                   $player1Score,
        public readonly ?int                   $player2Score,
        public readonly ?int                   $winnerId,
        public readonly ?PlayerDomain          $winner,
        public readonly ?WinnerDestinationSlot $winnerDestinationSlot,
        public readonly ?GameStatus            $status
    )
    {
    }

    public static function createForBracket(
        int                    $tournamentId,
        GameStage              $round,
        PlayoffSlot            $slot,
        ?WinnerDestinationSlot $winnerDestinationSlot = null
    ): PlayoffGameDomain
    {
        return new self(
            id: null,
            tournamentId: $tournamentId,
            tournament: null,
            round: $round,
            slot: $slot,
            player1Id: null,
            player2Id: null,
            player1: null,
            player2: null,
            player1Score: null,
            player2Score: null,
            winnerId: null,
            winner: null,
            winnerDestinationSlot: $winnerDestinationSlot,
            status: GameStatus::SCHEDULED
        );
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
            player1Score: $game->player1_score,
            player2Score: $game->player2_score,
            winnerId: $game->winner_id,
            winner: in_array('winner', $with)
                ? PlayerDomain::fromEloquent($game->winner)
                : null,
            winnerDestinationSlot: $game->winner_destination_slot,
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

    public function checkUpdateDataAccuracy(GameResultDTO $dto): void
    {
        if( $dto->player1Id !== $this->player1Id ||
            $dto->player2Id !== $this->player2Id)
        {
            throw new DomainException('Nieprawidłowe id graczy.');
        }

        if( !in_array($dto->winnerId, [$this->player1Id, $this->player2Id]) )
        {
            throw new DomainException('Id zwycięzcy nieprawidłowe');
        }

        if($this->status === GameStatus::FINISHED)
        {
            throw new DomainException('Mecz został już ukończony.');
        }

        if($dto->player1Score > $dto->player2Score)
        {
            if($dto->winnerId !== $this->player1Id)
            {
                throw new DomainException('Id zwycięzcy nieprawidłowe.');
            }
        } else {
            if($dto->winnerId !== $this->player2Id)
            {
                throw new DomainException('Id zwycięzcy nieprawidłowe.');
            }
        }
    }
}
