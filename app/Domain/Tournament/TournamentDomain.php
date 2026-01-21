<?php

namespace App\Domain\Tournament;

use App\Domain\AchievementDomain;
use App\Domain\Game\GroupGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\SeasonDomain;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TournamentDomain
{

    /**
     * @param int $id
     * @param string $name
     * @param Carbon|null $date
     * @param SeasonDomain|null $season
     * @param Carbon|null $updatedAt
     * @param Collection<AchievementDomain> $achievements
     * @param Collection<GroupGameDomain> $games
     * @param Collection<GroupStandingDomain> $groupStandings
     * @param TournamentStatus $status
     * @param PointSchemeDomain|null $pointScheme
     */
    public function __construct(
        public readonly int                 $id,
        public readonly string              $name,
        public readonly ?Carbon             $date,
        public readonly ?SeasonDomain       $season,
        public readonly ?Carbon             $updatedAt,
        public readonly Collection         $achievements,
        public readonly Collection         $games,
        public readonly Collection         $groupStandings,
        public readonly TournamentStatus    $status,
        public readonly ?PointSchemeDomain   $pointScheme,
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
        $tournament->loadMissing(array_intersect($with, ['season', 'achievements', 'games', 'groupStandings', 'pointScheme', 'pointScheme.rules']));

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
                ? $tournament->games->map(fn($game) => GroupGameDomain::fromEloquent($game))->values()
                : collect(),
            groupStandings: in_array('groupStandings', $with)
                ? $tournament->groupStandings->map(fn($group) => GroupStandingDomain::fromEloquent($group))->values()
                : collect(),
            status: $tournament->status,
            pointScheme: in_array('pointScheme', $with)
                ? PointSchemeDomain::fromEloquent(
                    $tournament->pointScheme,
                    with: in_array('pointScheme.rules', $with) ? ['rules'] : []
                )
                : null,
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

    public function isStarted(): bool
    {
        return $this->status !==  TournamentStatus::CREATED;
    }

    public function isPlayoff(): bool
    {
        return $this->status === TournamentStatus::PLAYOFF;
    }
}
