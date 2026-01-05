<?php
namespace App\Domain;

use App\Models\League;
use Carbon\Carbon;

class LeagueDomain
{

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
        public readonly array $admins,
        public readonly array $seasons,
        public readonly array $relatedUsers,
        public readonly array $guests
    )
    {}

    /**
     * @param League $league
     * @param array $with
     * @return self
     */
    public static function fromEloquent(League $league, array $with = []): self
    {
        $league->loadMissing(array_intersect($with, ['seasons', 'admins', 'relatedUsers', 'guests']));

        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            createdAt: $league->created_at,
            updatedAt: $league->updated_at,
            admins: in_array('admins', $with)
                ? $league->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name
                ])->toArray()
                : [],
            seasons: in_array('seasons', $with)
                ? $league->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))->toArray()
                : [],
            relatedUsers: in_array('relatedUsers', $with)
                ? $league->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            guests: in_array('guests', $with)
                ? $league->guests->map(fn($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name
                ])->toArray()
                : []
        );

    }

    public function updatedAtDate(): string
    {
        return $this->updatedAt->format('Y-m-d');
    }

    public function createdAtDate(): string
    {
        return $this->createdAt->format('Y-m-d');
    }

    public function getAdminsIds(): array
    {
        return array_column($this->admins, 'id');
    }
}
