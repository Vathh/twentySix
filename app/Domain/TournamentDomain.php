<?php

namespace App\Domain;

use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TournamentDomain
{

    public function __construct(
        public readonly int             $id,
        public readonly string          $name,
        public readonly ?Carbon         $date,
        public readonly ?SeasonDomain   $season,
        public readonly ?Carbon         $updatedAt,
        public readonly ?Collection     $achievements,
        public readonly ?Collection     $games,
        public readonly ?Collection     $groupStandings,
        public readonly TournamentStatus $status,
    )
    {
    }

    /**
     * @param Tournament $tournament
     * @param array $with
     * @return self
     */
    public static function fromEloquent(Tournament $tournament, array $with = []): self
    {
        $tournament->loadMissing(array_intersect($with, ['season', 'achievements', 'games', 'groupStandings']));

        return new self(
            id: $tournament->id,
            name: $tournament->name,
            date: $tournament->date,
            season: in_array('season', $with)
                ? SeasonDomain::fromEloquent($tournament->season)
                : null,
            updatedAt: $tournament->updated_at,
            achievements: in_array('achievements', $with)
                ? $tournament->achievements->map(fn($achievement) => AchievementDomain::fromEloquent($achievement))->values()
                : collect(),
            games: in_array('games', $with)
                ? $tournament->games->map(fn($game) => GameDomain::fromEloquent($game))->values()
                : collect(),
            groupStandings: in_array('groupStandings', $with)
                ? $tournament->groupStandings->map(fn($group) => GroupStandingDomain::fromEloquent($group))->values()
                : collect(),
            status: $tournament->status
        );
    }

    public function getDate(): ?string
    {
        return $this->date?->format('Y-m-d');
    }

    public function getUpdatedAtDate(): string
    {
        return $this->updatedAt?->format('Y-m-d');
    }
}
