<?php

namespace App\Domain;

use App\Domain\Tournament\TournamentDomain;
use App\Models\GroupStanding\GroupStanding;

class GroupStandingDomain
{
    public function __construct(
        public readonly int $id,
        public readonly ?TournamentDomain $tournament,
        public readonly int $groupNumber,
        public readonly ?PlayerDomain $player,
        public readonly int $gamesPlayed,
        public readonly int $gamesWon,
        public readonly int $gamesLost,
        public readonly int $matchUnitsWon,
        public readonly int $matchUnitsLost,
        public readonly int $points,
        public readonly int $matchUnitsDifference,
        public readonly int $place,
    ) {
    }

    public static function fromEloquent(GroupStanding $groupStanding, array $with = []): GroupStandingDomain
    {
        $groupStanding->loadMissing(array_intersect($with, ['tournament', 'player']));

        return new self(
            id: $groupStanding->id,
            tournament: in_array('tournament', $with)
                ? TournamentDomain::fromEloquent($groupStanding->tournament)
                : null,
            groupNumber: $groupStanding->group_number,
            player: in_array('player', $with)
                ? PlayerDomain::fromEloquent($groupStanding->player)
                : null,
            gamesPlayed: $groupStanding->games_played,
            gamesWon: $groupStanding->games_won,
            gamesLost: $groupStanding->games_lost,
            matchUnitsWon: $groupStanding->match_units_won,
            matchUnitsLost: $groupStanding->match_units_lost,
            points: $groupStanding->points,
            matchUnitsDifference: $groupStanding->match_units_difference,
            place: $groupStanding->place,
        );
    }

    public function withPlace(int $place): self
    {
        return new self(
            id: $this->id,
            tournament: $this->tournament,
            groupNumber: $this->groupNumber,
            player: $this->player,
            gamesPlayed: $this->gamesPlayed,
            gamesWon: $this->gamesWon,
            gamesLost: $this->gamesLost,
            matchUnitsWon: $this->matchUnitsWon,
            matchUnitsLost: $this->matchUnitsLost,
            points: $this->points,
            matchUnitsDifference: $this->matchUnitsDifference,
            place: $place,
        );
    }
}
