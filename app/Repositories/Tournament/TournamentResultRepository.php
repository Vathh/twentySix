<?php

namespace App\Repositories\Tournament;

use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\GameStage;
use App\Models\Tournament\TournamentResult;

class TournamentResultRepository
{
    public function createMany(array $tournamentResults): void
    {
        $mapped = array_map(fn (TournamentResultDomain $result) => [
            'season_id' => $result->seasonId,
            'tournament_id' => $result->tournamentId,
            'player_id' => $result->playerId,
            'points' => $result->points,
            'place' => $result->place,
            'elimination_stage' => $result->eliminationStage?->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $tournamentResults);

        TournamentResult::insert($mapped);
    }

    public function create(TournamentResultDomain $tournamentResult): void
    {
        $this->createMany([$tournamentResult]);
    }

    public function upsertForPlayer(
        ?int $seasonId,
        int $tournamentId,
        int $playerId,
        ?int $points,
        ?int $place,
        GameStage $stage,
    ): void {
        TournamentResult::updateOrCreate(
            [
                'tournament_id' => $tournamentId,
                'player_id' => $playerId,
            ],
            [
                'season_id' => $seasonId,
                'points' => $points,
                'place' => $place,
                'elimination_stage' => $stage->value,
            ],
        );
    }

    public function clearPodiumStage(int $tournamentId, GameStage $stage): void
    {
        TournamentResult::where('tournament_id', $tournamentId)
            ->where('elimination_stage', $stage->value)
            ->whereNotNull('place')
            ->delete();
    }
}












