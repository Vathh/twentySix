<?php

/** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\GroupStandingDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\GameStage;
use Illuminate\Support\Collection;

class TournamentResultsFactory
{
    /**
     * @param  Collection<int, GroupStandingDomain>  $groupStandings
     * @return Collection<int, TournamentResultDomain>
     */
    public function createManyForGroup(Collection $groupStandings, TournamentDomain $tournament): Collection
    {
        $seasonId = $tournament->tracksSeasonPoints() ? $tournament->season->id : null;

        return $groupStandings->map(fn (GroupStandingDomain $standing) => new TournamentResultDomain(
            season: null,
            seasonId: $seasonId,
            tournament: null,
            tournamentId: $standing->tournament->id,
            player: null,
            playerId: $standing->player->id,
            points: null,
            place: $standing->place,
            eliminationStage: GameStage::GROUP,
        ));
    }

    public function createForPlayoff(
        ?int $seasonId,
        int $tournamentId,
        int $playerId,
        ?int $points,
        ?int $place,
        GameStage $stage,
    ): TournamentResultDomain {
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
