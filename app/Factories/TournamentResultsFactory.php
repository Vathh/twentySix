<?php /** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\GroupStandingDomain;
use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\EliminationStage;
use Illuminate\Support\Collection;

class TournamentResultsFactory
{
    /**
     * @param Collection<GroupStandingDomain> $groupStandings
     * @param TournamentDomain $tournament
     * @return Collection<TournamentResultDomain>
     */
    public function createManyForGroup(Collection $groupStandings, TournamentDomain $tournament): Collection
    {
        $seasonId = $tournament->season->id;
        $pointSchemeRules = $tournament->pointScheme->rules;

        return $groupStandings->map(function ($standing) use ($seasonId, $pointSchemeRules) {
                    return $this->createForGroup(
                                standing: $standing,
                                seasonId: $seasonId,
                                points: $pointSchemeRules->where('elimination_stage', EliminationStage::GROUP->value)
                                    ->where('place', $standing->place)
                                    ->points
                            );
                });
    }

    public function createForGroup(GroupStandingDomain $standing, int $seasonId, int $points, EliminationStage $stage): TournamentResultDomain
    {
        return new TournamentResultDomain(
            seasonId: $seasonId,
            tournamentId: $standing->tournament->id,
            playerId: $standing->player->id,
            points: $points,
            place: $standing->place,
            eliminationStage: EliminationStage::GROUP,
        );
    }

    public function createForPlayoff()
}
