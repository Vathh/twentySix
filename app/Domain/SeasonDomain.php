<?php
namespace App\Domain;

use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        public readonly array $relatedUsers,
        public readonly Collection $tournaments,
        public readonly array $guests
    )
    {
    }

    /**
     * @param Season $season
     * @param array $with
     * @return self
     */
    public static function fromEloquent(Season $season, array $with = []): self
    {
        $season->loadMissing(array_intersect($with, ['league', 'admins', 'relatedUsers', 'tournaments']));

        return new self(
            id: $season->id,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            updatedAt: $season->updated_at,
            admins: in_array('admins', $with)
                ? $season->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name
                ])->toArray()
                : [],
            league: in_array('league', $with)
                ? LeagueDomain::fromEloquent($season->league)
                : null,
            relatedUsers: in_array('relatedUsers', $with)
                ? $season->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name
                ])->toArray()
                : [],
            tournaments: in_array('tournaments', $with)
                ? $season->tournaments->map(fn($tournament) => TournamentDomain::fromEloquent($tournament))->values()
                : collect(),
            guests: in_array('guests', $with)
                ? $season->guests->map(fn($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name
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

    public function getUpdatedAtDate(): string
    {
        return $this->updatedAt->format('Y-m-d');
    }
}
