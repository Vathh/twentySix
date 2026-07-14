<?php

namespace App\Domain\Tournament;

use App\Domain\AchievementDomain;
use App\Domain\Game\GroupGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\SeasonDomain;
use App\Enums\TournamentStatus;
use App\Models\Tournament\Tournament;
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
     * @param int|null $groupsCount
     * @param int|null $playoffBracketSize
     * @param list<int>|null $groupAdvances
     * @param int|null $tabletsCount
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
        public readonly ?int                $groupsCount = null,
        public readonly ?int                $playoffBracketSize = null,
        public readonly ?array              $groupAdvances = null,
        public readonly ?int                $tabletsCount = null,
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
        $relations = array_intersect($with, ['season', 'achievements', 'games', 'groupStandings', 'pointScheme', 'pointScheme.rules']);
        if (in_array('season', $relations, true)) {
            $relations = array_values(array_diff($relations, ['season']));
            $relations[] = 'season.league';
        }
        $tournament->loadMissing($relations);

        return new self(
            id: $tournament->id,
            name: $tournament->name,
            date: $tournament->date,
            season: in_array('season', $with) && $tournament->season
                ? SeasonDomain::fromEloquent($tournament->season, ['league'])
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
            pointScheme: in_array('pointScheme', $with) && $tournament->pointScheme
                ? PointSchemeDomain::fromEloquent(
                    $tournament->pointScheme,
                    with: in_array('pointScheme.rules', $with) ? ['rules'] : []
                )
                : null,
            groupsCount: $tournament->groups_count,
            playoffBracketSize: $tournament->playoff_bracket_size,
            groupAdvances: $tournament->group_advances,
            tabletsCount: $tournament->tablets_count,
        );
    }

    /** Liczba graczy w drabince playoff. Null przed startem turnieju. */
    public function bracketSize(): ?int
    {
        return $this->playoffBracketSize;
    }

    public function hasStartConfiguration(): bool
    {
        return $this->groupsCount !== null
            && $this->playoffBracketSize !== null
            && is_array($this->groupAdvances)
            && $this->groupAdvances !== []
            && $this->tabletsCount !== null;
    }

    public function getDate(): ?string
    {
        return $this->date?->format('Y-m-d');
    }

    /** Nagłówek listy: „Liga – nazwa turnieju”, jeśli znana jest liga sezonu. */
    public function displayTitle(): string
    {
        $leagueName = $this->season?->league?->name;
        if (is_string($leagueName) && $leagueName !== '') {
            return $leagueName.' - '.$this->name;
        }

        return $this->name;
    }

    public function getPlayDateFormatted(): ?string
    {
        if ($this->date === null) {
            return null;
        }

        return $this->date->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    public function getUpdatedAtDate(): string
    {
        return $this->updatedAt?->format('Y-m-d');
    }

    public function isStarted(): bool
    {
        return $this->status !==  TournamentStatus::CREATED;
    }

    public function hasPlayoffBracket(): bool
    {
        return in_array($this->status, [TournamentStatus::PLAYOFF, TournamentStatus::FINISHED]);
    }
}

