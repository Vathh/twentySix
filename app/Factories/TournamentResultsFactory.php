<?php /** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\GroupStandingDomain;
use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\GameStage;
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
        $pointSchemeRules = $tournament->pointScheme->rules->filter(fn($rule) => $rule->stage === GameStage::GROUP);

        return $groupStandings->map(function ($standing) use ($seasonId, $pointSchemeRules) {
                    return $this->createForGroup(
                                standing: $standing,
                                seasonId: $seasonId,
                                points: $pointSchemeRules->first(fn($rule) => $rule->place === $standing->place)->points
                            );
                });
    }

    /**
     * @param Collection<GroupStandingDomain> $groupStandings
     * @return Collection<TournamentResultDomain>
     */
    public function createManyForGroupWithoutPoints(Collection $groupStandings, TournamentDomain $tournament): Collection
    {
        return $groupStandings->map(fn ($standing) => new TournamentResultDomain(
            season: null,
            seasonId: null,
            tournament: null,
            tournamentId: $standing->tournament->id,
            player: null,
            playerId: $standing->player->id,
            points: null,
            place: $standing->place,
            eliminationStage: GameStage::GROUP,
        ));
    }

    private function createForGroup(GroupStandingDomain $standing, int $seasonId, int $points): TournamentResultDomain
    {
        return new TournamentResultDomain(
            season: null,
            seasonId: $seasonId,
            tournament: null,
            tournamentId: $standing->tournament->id,
            player: null,
            playerId: $standing->player->id,
            points: $points,
            place: $standing->place,
            eliminationStage: GameStage::GROUP,
        );
    }

    /**
     * @param int $seasonId
     * @param int $tournamentId
     * @param int $playerId
     * @param int|null $points
     * @param int|null $place
     * @param GameStage $stage
     * @return TournamentResultDomain
     */
    public function createForPlayoff(?int      $seasonId,
                                     int       $tournamentId,
                                     int       $playerId,
                                     ?int      $points,
                                     ?int      $place,
                                     GameStage $stage): TournamentResultDomain
    {
        return new TournamentResultDomain(
            season: null,
            seasonId: $seasonId,
            tournament: null,
            tournamentId: $tournamentId,
            player: null,
            playerId: $playerId,
            points: $points,
            place: $place,
            eliminationStage: $stage
        );
    }
}

