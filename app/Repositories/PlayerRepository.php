<?php

namespace App\Repositories;

use App\Domain\PlayerDomain;
use App\Models\Player;

class PlayerRepository
{
    public function create(string $name, int $userId): PlayerDomain
    {
        $player = Player::create([
            'name' => $name,
            'user_id' => $userId,
        ]);

        return PlayerDomain::fromEloquent($player);
    }
}
