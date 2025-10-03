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
        public readonly Carbon $updatedAt,
        public readonly array $admins,
        public readonly array $seasons,
    )
    {}

    public static function fromEloquent(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            admins: [],
            seasons: $league->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))
                                     ->toArray(),
            updatedAt: $league->updated_at
        );
    }

    public static function fromEloquentWithAdmins(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            updatedAt: $league->updated_at,
            admins: $league->admins->map(fn($admin) => [

                ])->toArray()
        );
    }
}
