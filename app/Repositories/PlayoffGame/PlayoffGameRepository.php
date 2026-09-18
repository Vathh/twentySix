<?php

namespace App\Repositories\PlayoffGame;

use App\Domain\Game\PlayoffGameDomain;
use App\Domain\Game\WinnerDestination;
use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Models\PlayoffGame\PlayoffGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayoffGameRepository
{
    /**
     * @param  Collection<PlayoffGameDomain>  $games
     * @param  array<string, MatchFormat>  $formatsByStage
     */
    public function createMany(Collection $games, array $formatsByStage = []): void
    {
        foreach ($games as $game) {
            $format = $formatsByStage[$game->round] ?? MatchFormat::default();

            PlayoffGame::create(array_merge([
                'tournament_id' => $game->tournamentId,
                'bracket_side' => $game->bracketSide,
                'round' => $game->round,
                'slot' => $game->slot,
                'player1_id' => $game->player1Id ?: null,
                'player2_id' => $game->player2Id ?: null,
                'winner_destination_slot' => $game->winnerDestinationSlot ?: null,
                'loser_destination_slot' => $game->loserDestinationSlot ?: null,
            ], $format->toDatabaseColumns()));
        }
    }

    public function finish(GameResultDTO $dto): void
    {
        DB::table('playoff_games')
            ->where('id', $dto->gameId)
            ->update([
                'player1_score' => $dto->player1Score,
                'player2_score' => $dto->player2Score,
                'player1_legs_in_set' => 0,
                'player2_legs_in_set' => 0,
                'current_set_number' => 1,
                'winner_id' => $dto->winnerId,
                'status' => GameStatus::FINISHED,
            ]);
    }

    public function tryLockScheduled(int $gameId): bool
    {
        return PlayoffGame::query()
            ->where('id', $gameId)
            ->where('status', GameStatus::SCHEDULED)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->whereHas('player1', fn ($q) => $q->where('is_bye', false))
            ->whereHas('player2', fn ($q) => $q->where('is_bye', false))
            ->update(['status' => GameStatus::IN_PROGRESS]) === 1;
    }

    public function tryUnlockInProgress(int $gameId): bool
    {
        return DB::table('playoff_games')
            ->where('id', $gameId)
            ->where('status', GameStatus::IN_PROGRESS)
            ->update(['status' => GameStatus::SCHEDULED]) === 1;
    }

    public function isInProgress(int $gameId): bool
    {
        return PlayoffGame::query()
            ->where('id', $gameId)
            ->where('status', GameStatus::IN_PROGRESS)
            ->exists();
    }

    /**
     * @return Collection<PlayoffGameDomain>
     */
    public function getActive(int $tournamentId): Collection
    {
        return PlayoffGame::with(['tournament', 'player1', 'player2'])
            ->where('tournament_id', $tournamentId)
            ->whereIn('status', [GameStatus::SCHEDULED, GameStatus::IN_PROGRESS])
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->whereHas('player1', fn ($q) => $q->where('is_bye', false))
            ->whereHas('player2', fn ($q) => $q->where('is_bye', false))
            ->get()
            ->map(fn ($game) => PlayoffGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']));
    }

    /**
     * @return Collection<PlayoffGameDomain>
     */
    public function getAllForTournament(int $tournamentId): Collection
    {
        return PlayoffGame::with(['player1', 'player2'])
            ->where('tournament_id', $tournamentId)
            ->get()
            ->map(fn ($game) => PlayoffGameDomain::fromEloquent($game, ['player1', 'player2']));
    }

    public function find(int $id): ?PlayoffGameDomain
    {
        return PlayoffGameDomain::fromEloquent(PlayoffGame::where('id', $id)->firstOrFail());
    }

    /**
     * @param  string[]  $relations
     */
    public function findModel(int $gameId, array $relations = []): PlayoffGame
    {
        return PlayoffGame::with($relations)->findOrFail($gameId);
    }

    public function save(PlayoffGame $game): void
    {
        $game->save();
    }

    public function setPlayer1Slot(int $tournamentId, string $slot, int $playerId): void
    {
        $this->assignPlayerToSlot($tournamentId, $slot, $playerId, 'player1_id');
    }

    public function setPlayer2Slot(int $tournamentId, string $slot, int $playerId): void
    {
        $this->assignPlayerToSlot($tournamentId, $slot, $playerId, 'player2_id');
    }

    private function assignPlayerToSlot(int $tournamentId, string $slot, int $playerId, string $column): void
    {
        $game = PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->first();

        if ($game === null) {
            throw new \DomainException("Brak meczu w slocie {$slot}.");
        }

        if ($game->status !== GameStatus::SCHEDULED) {
            throw new \DomainException("Nie można wpisać zawodnika do meczu, który nie jest oczekujący ({$slot}).");
        }

        $game->{$column} = $playerId;
        $game->save();
    }

    public function resetFinishedBranchFromSlot(int $tournamentId, string $slot): void
    {
        $game = PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->first();

        if ($game === null || $game->status !== GameStatus::FINISHED) {
            return;
        }

        $destinationSlot = null;
        if ($game->winner_destination_slot !== null) {
            $destinationSlot = WinnerDestination::parse((string) $game->winner_destination_slot)->playoffSlot;
        }

        $game->update([
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'status' => GameStatus::SCHEDULED,
        ]);

        if ($destinationSlot !== null) {
            $this->resetFinishedBranchFromSlot($tournamentId, $destinationSlot);
        }
    }

    /**
     * @return Collection<string, int>
     */
    public function countByRoundForTournament(int $tournamentId): Collection
    {
        return PlayoffGame::where('tournament_id', $tournamentId)
            ->where('bracket_side', '!=', \App\Enums\BracketSide::Consolation->value)
            ->get()
            ->countBy(fn (PlayoffGame $game) => $game->round instanceof \App\Enums\GameStage
                ? $game->round->value
                : (string) $game->round);
    }

    /**
     * @return Collection<int, PlayoffGame>
     */
    public function findInProgressForPlayer(int $playerId): Collection
    {
        return PlayoffGame::query()
            ->with(['player1:id,name', 'player2:id,name', 'tournament:id,name'])
            ->where('status', GameStatus::IN_PROGRESS)
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->get();
    }

    /**
     * Bye vs BYE oraz gracz vs BYE — oba sloty wypełnione, co najmniej jeden to BYE.
     *
     * @return Collection<int, PlayoffGameDomain>
     */
    public function getScheduledByeGames(int $tournamentId, int $byePlayerId): Collection
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('status', GameStatus::SCHEDULED)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->where(function ($q) use ($byePlayerId) {
                $q->where('player1_id', $byePlayerId)->orWhere('player2_id', $byePlayerId);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (PlayoffGame $game) => PlayoffGameDomain::fromEloquent($game));
    }

    /**
     * Surowy mecz playoff po slocie (np. GF2), albo null.
     */
    public function findModelByTournamentSlot(int $tournamentId, string $slot): ?PlayoffGame
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->first();
    }

    /**
     * @return Collection<int, PlayoffGame>
     */
    public function listFinished(): Collection
    {
        return PlayoffGame::query()
            ->where('status', GameStatus::FINISHED)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('id')
            ->get();
    }
}
