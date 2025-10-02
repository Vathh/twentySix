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
        public readonly int $leagueId,
        public readonly string $name,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly array $admins,
        public readonly Carbon $updatedAt
    )
    {
    }

    public static function fromEloquent(Season $season): self
    {
        return new self(
            id: $season->id,
            leagueId: $season->league_id,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            admins: [],
            updatedAt: $season->updated_at
        );
    }

    public static function fromEloquentWithAdmins(Season $season): self
    {
        return new self(
          id: $season->id,
            leagueId: $season->league_id,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            admins: $season->admins->map(fn($admin) => [
                    'id' => $admin->id,
                    'name' => $admin->name
                ])->toArray(),
            updatedAt: $season->updated_at
        );
    }
}
