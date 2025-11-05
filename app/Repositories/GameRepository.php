<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class GameRepository
{
    /**
     * @throws \Throwable
     */
    public function createGames(array $games): void
    {
        DB::table('games')->insert($games);
    }
}
