<?php
namespace App\Domain;

use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use Carbon\Carbon;

class SeasonDomain
{

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly Carbon $updatedAt,
        public readonly array $admins,
        public readonly ?LeagueDomain $league,
        public readonly array $relatedUsers
    )
    {
    }

    public static function fromEloquent(Season $season, array $with = []): self
    {
        $season->loadMissing(array_intersect($with, ['league', 'admins', 'relatedUsers']));

        return new self(
            id: $season->id,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            updatedAt: $season->updated_at,
            admins: in_array('admins', $with)
                ? $season->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name
                ])->toArray()
                : [],
            league: in_array('league', $with)
                ? LeagueDomain::fromEloquent($season->league ?? new League(), [])
                : null,
            relatedUsers: in_array('relatedUsers', $with)
                ? $season->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name
                ])->toArray()
                : [],
        );
    }

    public static function fromEloquentWithAdmins(Season $season, bool $withRelations = true): self
    {
        $season->loadMissing('admins.player');

        if($withRelations) {
            $season->loadMissing('league', 'relatedUsers.player');
        }

        return new self(
            id: $season->id,
            league: $withRelations
                ? LeagueDomain::fromEloquent($season->league, false)
                : null,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            admins: $season->admins->map(fn($admin) => [
                    'id' => $admin->id,
                    'name' => $admin->player->name
                ])->toArray(),
            updatedAt: $season->updated_at,
            relatedUsers: $withRelations
                ? $season->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name
                ])->toArray()
                : []
        );
    }

    public function getStartDate(): ?string
    {
        return $this->startDate?->format('Y-m-d');
    }

    public function getEndDate(): ?string
    {
        return $this->endDate?->format('Y-m-d');
    }

    public function updatedAtDate(): string
    {
        return $this->updatedAt->format('Y-m-d');
    }
}
