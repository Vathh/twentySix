<?php

namespace App\Domain;

use App\Domain\Tournament\TournamentDomain;
use App\Models\GroupStanding\GroupStanding;

class GroupStandingDomain
{
    /**
     * @param int $id
     * @param TournamentDomain|null $tournament
     * @param int $groupNumber
     * @param PlayerDomain|null $player
     * @param int $gamesPlayed
     * @param int $gamesWon
     * @param int $gamesLost
     * @param int $legsWon
     * @param int $legsLost
     * @param int $points
     * @param int $legsDifference
     * @param int $place
     */
    public function __construct(
        public readonly int $id,
        public readonly ?TournamentDomain $tournament,
        public readonly int $groupNumber,
        public readonly ?PlayerDomain $player,
        public readonly int $gamesPlayed,
        public readonly int $gamesWon,
        public readonly int $gamesLost,
        public readonly int $legsWon,
        public readonly int $legsLost,
        public readonly int $points,
        public readonly int $legsDifference,
        public readonly int $place
    )
    {
    }

    /**
     * @param GroupStanding $groupStanding
     * @param array $with
     * @return GroupStandingDomain
     */
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
            legsWon: $groupStanding->legs_won,
            legsLost: $groupStanding->legs_lost,
            points: $groupStanding->points,
            legsDifference: $groupStanding->legs_difference,
            place: $groupStanding->place
        );
    }

    /**
     * @param int $place
     * @return self
     */
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
            legsWon: $this->legsWon,
            legsLost: $this->legsLost,
            points: $this->points,
            legsDifference: $this->legsDifference,
            place: $place
        );
    }
}

