<?php

namespace App\Domain\Game;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\PlayerDomain;
use App\Domain\Tournament\TournamentDomain;
use App\DTO\GameResultDTO;
use App\Enums\BracketSide;
use App\Enums\GameStatus;
use App\Enums\GameStage;
use App\Models\PlayoffGame\PlayoffGame;
use App\Support\Tournament\PlayoffRoundLabel;

class PlayoffGameDomain extends GameDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['tournament', 'player1', 'player2', 'winner'];

    public function __construct(
        ?int $id,
        public readonly ?int $tournamentId,
        public readonly ?TournamentDomain $tournament,
        public readonly string $round,
        public readonly string $slot,
        public readonly BracketSide $bracketSide,
        public readonly ?int $player1Id,
        public readonly ?int $player2Id,
        ?PlayerDomain $player1,
        ?PlayerDomain $player2,
        ?int $player1Score,
        ?int $player2Score,
        public readonly ?int $winnerId,
        ?PlayerDomain $winner,
        public readonly ?string $winnerDestinationSlot,
        public readonly ?string $loserDestinationSlot,
        ?GameStatus $status
    ) {
        parent::__construct(
            id: $id,
            player1: $player1,
            player2: $player2,
            player1Score: $player1Score,
            player2Score: $player2Score,
            winner: $winner,
            status: $status ?? GameStatus::SCHEDULED
        );
    }

    public static function createForBracket(
        int $tournamentId,
        GameStage|string $round,
        string $slot,
        ?string $winnerDestinationSlot = null,
        BracketSide $bracketSide = BracketSide::Main,
        ?string $loserDestinationSlot = null,
    ): PlayoffGameDomain {
        return new self(
            id: null,
            tournamentId: $tournamentId,
            tournament: null,
            round: $round instanceof GameStage ? $round->value : $round,
            slot: $slot,
            bracketSide: $bracketSide,
            player1Id: null,
            player2Id: null,
            player1: null,
            player2: null,
            player1Score: null,
            player2Score: null,
            winnerId: null,
            winner: null,
            winnerDestinationSlot: $winnerDestinationSlot,
            loserDestinationSlot: $loserDestinationSlot,
            status: GameStatus::SCHEDULED
        );
    }

    public static function fromEloquent(PlayoffGame $game, array $with = []): PlayoffGameDomain
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

        $side = $game->bracket_side instanceof BracketSide
            ? $game->bracket_side
            : BracketSide::tryFrom((string) ($game->bracket_side ?? 'main')) ?? BracketSide::Main;

        $round = $game->round instanceof GameStage
            ? $game->round->value
            : (string) $game->round;

        return new self(
            id: $game->id,
            tournamentId: $game->tournament_id,
            tournament: in_array('tournament', $with) && $game->tournament
                ? TournamentDomain::fromEloquent($game->tournament)
                : null,
            round: $round,
            slot: (string) $game->slot,
            bracketSide: $side,
            player1Id: $game->player1_id,
            player2Id: $game->player2_id,
            player1: $player1,
            player2: $player2,
            player1Score: $game->player1_score ?? ($game->status !== GameStatus::SCHEDULED ? 0 : null),
            player2Score: $game->player2_score ?? ($game->status !== GameStatus::SCHEDULED ? 0 : null),
            winnerId: $game->winner_id,
            winner: $winner,
            winnerDestinationSlot: $game->winner_destination_slot !== null
                ? (string) $game->winner_destination_slot
                : null,
            loserDestinationSlot: $game->loser_destination_slot !== null
                ? (string) $game->loser_destination_slot
                : null,
            status: $game->status
        );
    }

    public function withPlayerIds(?int $player1Id, ?int $player2Id): PlayoffGameDomain
    {
        return new self(
            id: $this->id,
            tournamentId: $this->tournamentId,
            tournament: $this->tournament,
            round: $this->round,
            slot: $this->slot,
            bracketSide: $this->bracketSide,
            player1Id: $player1Id,
            player2Id: $player2Id,
            player1: $this->player1,
            player2: $this->player2,
            player1Score: $this->player1Score,
            player2Score: $this->player2Score,
            winnerId: $this->winnerId,
            winner: $this->winner,
            winnerDestinationSlot: $this->winnerDestinationSlot,
            loserDestinationSlot: $this->loserDestinationSlot,
            status: $this->status
        );
    }

    public function roundStage(): ?GameStage
    {
        return GameStage::tryFrom($this->round);
    }

    public function roundLabel(): string
    {
        return PlayoffRoundLabel::label($this->round);
    }

    public function isByeReady(): bool
    {
        return ($this->player1Id !== null) xor ($this->player2Id !== null);
    }

    public function byeWinnerId(): ?int
    {
        if (! $this->isByeReady()) {
            return null;
        }

        return $this->player1Id ?? $this->player2Id;
    }

    public function playerIds(): array
    {
        return [
            $this->player1Id ?? 0,
            $this->player2Id ?? 0
        ];
    }

    public function checkUpdateDataAccuracy(GameResultDTO $dto): void
    {
        if ($dto->player1Id !== $this->player1Id || $dto->player2Id !== $this->player2Id) {
            throw new \DomainException('Nieprawidłowe id graczy.');
        }

        $this->validateWinner($dto->winnerId);
        $this->validateNotFinished();

        if ($dto->player1Score > $dto->player2Score) {
            if ($dto->winnerId !== $this->player1Id) {
                throw new \DomainException('Id zwycięzcy nieprawidłowe.');
            }
        } else {
            if ($dto->winnerId !== $this->player2Id) {
                throw new \DomainException('Id zwycięzcy nieprawidłowe.');
            }
        }
    }
}
