<?php

namespace App\Repositories\Game;

use App\DTO\GameScoring\RecordVisitDTO;
use App\Models\Game\GameVisit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'darts' => $dto->darts,
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
            'darts' => $dto->darts,
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

    public function hasActiveCheckout(int $gameLegId): bool
    {
        return GameVisit::query()
            ->where('game_leg_id', $gameLegId)
            ->where('is_voided', false)
            ->where('closed_leg', true)
            ->where('bust', false)
            ->where('remaining_after', 0)
            ->exists();
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

    /**
     * Monotoniczna generacja stanu (undo nie może jej zmniejszyć).
     * max(id) zostaje po void, liczba voidów rośnie.
     *
     * @param  list<int>  $gameLegIds
     */
    public function scoringStateVersionForGameLegs(array $gameLegIds): int
    {
        if ($gameLegIds === []) {
            return 0;
        }

        $maxId = (int) (GameVisit::query()
            ->whereIn('game_leg_id', $gameLegIds)
            ->max('id') ?? 0);
        $voided = (int) GameVisit::query()
            ->whereIn('game_leg_id', $gameLegIds)
            ->where('is_voided', true)
            ->count();

        return ($maxId * 10_000) + $voided;
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

    /**
     * @param  list<int>  $gameLegIds
     */
    public function deleteForLegIds(array $gameLegIds): void
    {
        if ($gameLegIds === []) {
            return;
        }

        GameVisit::query()->whereIn('game_leg_id', $gameLegIds)->delete();
    }

    public function nextVisitNumber(int $gameLegId): int
    {
        $max = GameVisit::query()
            ->where('game_leg_id', $gameLegId)
            ->max('visit_number');

        return ($max ?? 0) + 1;
    }

    /**
     * @param  list<int>  $leagueGameIds
     * @return Collection<int, object{player_id: int, count_180: int, count_170_plus: int, best_checkout: ?int}>
     */
    public function highlightsForLeagueGames(array $leagueGameIds): Collection
    {
        if ($leagueGameIds === []) {
            return collect();
        }

        return DB::table('game_visits as gv')
            ->join('game_legs as gl', 'gl.id', '=', 'gv.game_leg_id')
            ->whereIn('gl.league_game_id', $leagueGameIds)
            ->where('gv.is_voided', false)
            ->where('gv.bust', false)
            ->groupBy('gv.player_id')
            ->selectRaw('gv.player_id as player_id')
            ->selectRaw('SUM(CASE WHEN gv.score = 180 THEN 1 ELSE 0 END) as count_180')
            ->selectRaw('SUM(CASE WHEN gv.closed_leg = 1 AND gv.score >= 170 THEN 1 ELSE 0 END) as count_170_plus')
            ->selectRaw('MAX(CASE WHEN gv.closed_leg = 1 THEN gv.score ELSE NULL END) as best_checkout')
            ->get()
            ->keyBy(fn ($row) => (int) $row->player_id);
    }
}
