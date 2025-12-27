<?php

namespace App\Domain;

use App\Enums\AchievementType;
use App\Models\Achievement;

class AchievementDomain
{

    public function __construct(
        public readonly int $id,
        public readonly ?TournamentDomain $tournament,
        public readonly ?PlayerDomain $player,
        public readonly AchievementType $type,
        public readonly ?int $value
    )
    {}

    public static function fromEloquent(Achievement $achievement, array $with = []): AchievementDomain
    {
        $achievement->loadMissing(array_intersect($with, ['tournament', 'player']));

        return new self(
            id: $achievement->id,
            tournament: in_array('tournament', $with)
                ? TournamentDomain::fromEloquent($achievement->tournament)
                : null,
            player: in_array('player', $with)
                ? PlayerDomain::fromEloquent($achievement->player)
                : null,
            type: $achievement->type,
            value: $achievement->value
        );
    }
}
