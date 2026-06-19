<?php

namespace App\Repositories\Game;

use App\DTO\GameScoring\RecordVisitDTO;
use App\Models\Game\GameVisit;
use Illuminate\Support\Collection;

class GameVisitRepository
{
    public function findByClientVisitId(string $clientVisitId): ?GameVisit
    {
        return GameVisit::query()->where('client_visit_id', $clientVisitId)->first();
    }

    public function create(int $gameLegId, int $visitNumber, RecordVisitDTO $dto): GameVisit
    {
        return GameVisit::create([
            'game_leg_id' => $gameLegId,
            'player_id' => $dto->playerId,
            'visit_number' => $visitNumber,
            'score' => $dto->score,
            'remaining_before' => $dto->remainingBefore,
            'remaining_after' => $dto->remainingAfter,
            'darts_in_visit' => $dto->dartsInVisit,
            'closed_leg' => $dto->closedLeg,
            'bust' => $dto->bust,
            'is_voided' => false,
            'client_visit_id' => $dto->clientVisitId,
        ]);
    }

    public function updateFromDto(GameVisit $visit, RecordVisitDTO $dto): GameVisit
    {
        $visit->update([
            'score' => $dto->score,
            'remaining_before' => $dto->remainingBefore,
            'remaining_after' => $dto->remainingAfter,
            'darts_in_visit' => $dto->dartsInVisit,
            'closed_leg' => $dto->closedLeg,
            'bust' => $dto->bust,
        ]);

        return $visit->fresh();
    }

    /**
     * @return Collection<int, GameVisit>
     */
    public function getActiveForLeg(int $gameLegId): Collection
    {
        return GameVisit::query()
            ->where('game_leg_id', $gameLegId)
            ->where('is_voided', false)
            ->orderBy('visit_number')
            ->orderBy('id')
            ->get();
    }

    public function countActiveForGameLegs(array $gameLegIds): int
    {
        if ($gameLegIds === []) {
            return 0;
        }

        return GameVisit::query()
            ->whereIn('game_leg_id', $gameLegIds)
            ->where('is_voided', false)
            ->count();
    }

    /**
     * @return Collection<int, GameVisit>
     */
    public function getActiveForGameLegs(array $gameLegIds): Collection
    {
        if ($gameLegIds === []) {
            return collect();
        }

        return GameVisit::query()
            ->whereIn('game_leg_id', $gameLegIds)
            ->where('is_voided', false)
            ->orderBy('game_leg_id')
            ->orderBy('visit_number')
            ->orderBy('id')
            ->get();
    }

    public function voidLastForLeg(int $gameLegId): ?GameVisit
    {
        $visit = GameVisit::query()
            ->where('game_leg_id', $gameLegId)
            ->where('is_voided', false)
            ->orderByDesc('visit_number')
            ->orderByDesc('id')
            ->first();

        if ($visit === null) {
            return null;
        }

        $visit->update(['is_voided' => true]);

        return $visit;
    }

    public function nextVisitNumber(int $gameLegId): int
    {
        $max = GameVisit::query()
            ->where('game_leg_id', $gameLegId)
            ->max('visit_number');

        return ($max ?? 0) + 1;
    }
}
