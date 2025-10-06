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
    )
    {}

    public static function fromEloquent(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            createdAt: $league->created_at,
            updatedAt: $league->updated_at,
            admins: [],
            seasons: $league->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))
                                     ->toArray(),
            relatedUsers: $league->relatedUsers->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->toArray(),
        );
    }

    public static function fromEloquentWithAdmins(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            createdAt: $league->created_at,
            updatedAt: $league->updated_at,
            admins: $league->admins->map(fn($admin) => [
                'id' => $admin->id,
                'name' => $admin->name
            ])->toArray(),
            seasons: $league->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))
                ->toArray(),
            relatedUsers: $league->relatedUsers->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->toArray(),
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
}
