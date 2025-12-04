<?php

namespace App\Domain;

use App\Models\GroupStanding;

class GroupStandingDomain
{

    public function __construct(
        public readonly int $id,
        public readonly ?TournamentDomain $tournament,
        public readonly int $groupNumber,
        public readonly ?PlayerDomain $player,
        public readonly int $matchesPlayed,
        public readonly int $matchesWon,
        public readonly int $matchesLost,
        public readonly int $legsWon,
        public readonly int $legsLost,
        public readonly int $points,
        public readonly int $legsDifference,
        public readonly int $place
    )
    {
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
            matchesPlayed: $groupStanding->matches_played,
            matchesWon: $groupStanding->matches_won,
            matchesLost: $groupStanding->matches_lost,
            legsWon: $groupStanding->legs_won,
            legsLost: $groupStanding->legs_lost,
            points: $groupStanding->points,
            legsDifference: $groupStanding->legs_difference,
            place: $groupStanding->place
        );
    }

    public function withPlace(int $place): self
    {
        return new self(
            id: $this->id,
            tournament: $this->tournament,
            groupNumber: $this->groupNumber,
            player: $this->player,
            matchesPlayed: $this->matchesPlayed,
            matchesWon: $this->matchesWon,
            matchesLost: $this->matchesLost,
            legsWon: $this->legsWon,
            legsLost: $this->legsLost,
            points: $this->points,
            legsDifference: $this->legsDifference,
            place: $place
        );
    }
}
