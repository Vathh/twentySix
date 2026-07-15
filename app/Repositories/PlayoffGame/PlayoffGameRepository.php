<?php

namespace App\Repositories\PlayoffGame;

use App\Domain\Game\PlayoffGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Enums\PlayerSlot;
use App\Enums\PlayoffSlot;
use App\Models\PlayoffGame\PlayoffGame;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayoffGameRepository
{
    /**
     * @param Collection<PlayoffGameDomain> $games
     * @param  array<string, MatchFormat>  $formatsByStage
     */
    public function createMany(Collection $games, array $formatsByStage = []): void
    {
        foreach ($games as $game) {
            $format = $formatsByStage[$game->round->value] ?? MatchFormat::default();

            PlayoffGame::create(array_merge([
                'tournament_id' => $game->tournamentId,
                'round' => $game->round,
                'slot' => $game->slot,
                'player1_id' => $game->player1Id ?: null,
                'player2_id' => $game->player2Id ?: null,
                'winner_destination_slot' => $game->winnerDestinationSlot ?: null,
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
        return DB::table('playoff_games')
            ->where('id', $gameId)
            ->where('status', GameStatus::SCHEDULED)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
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
     * @param int $tournamentId
     * @return Collection<PlayoffGameDomain>
     */
    public function getActive(int $tournamentId): Collection
    {
        return PlayoffGame::with(['tournament', 'player1', 'player2'])
                            ->where('tournament_id', $tournamentId)
                            ->whereIn('status', [GameStatus::SCHEDULED, GameStatus::IN_PROGRESS])
                            ->get()
                            ->map(fn($game) => PlayoffGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']));
    }

    public function find(int $id): ?PlayoffGameDomain
    {
        return PlayoffGameDomain::fromEloquent(PlayoffGame::where('id', $id)->firstOrFail());
    }

    public function setPlayer1Slot(int $tournamentId, PlayoffSlot $slot, int $playerId): void
    {
        PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->update(['player1_id' => $playerId]);
    }

    public function setPlayer2Slot(int $tournamentId, PlayoffSlot $slot, int $playerId): void
    {
        PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->update(['player2_id' => $playerId]);
    }

    public function resetFinishedBranchFromSlot(int $tournamentId, PlayoffSlot $slot): void
    {
        $game = PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->first();

        if ($game === null || $game->status !== GameStatus::FINISHED) {
            return;
        }

        $destinationSlot = $game->winner_destination_slot?->toDestination()?->playoffSlot;

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
}












