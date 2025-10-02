<?php
namespace App\Domain;

use App\Models\League;
use App\Models\Player;
use Carbon\Carbon;

class PlayerDomain
{

    public function __construct(
        public readonly int $id,
        public readonly string $name
    )
    {}

    public static function fromEloquent(Player $player): self
    {
        return new self(
            id: $player->id,
            name: $player->name
        );
    }
}
