<?php
namespace App\Domain;

use App\Models\League;
use Carbon\Carbon;

class LeagueDomain
{

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Carbon $updatedAt,
        public readonly array $admins
    )
    {}

    public static function fromEloquent(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            admins: [],
            updatedAt: $league->updated_at
        );
    }

    public static function fromEloquentWithAdmins(League $league): self
    {
        return new self(
            id: $league->id,
            name: $league->name,
            updatedAt: $league->updated_at,
            admins: $league->admins->map(fn($admin) => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                ])->toArray()
        );
    }
}
