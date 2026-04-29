<?php

namespace App\Repositories\Tournament;

use App\Models\LoginCode;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoginCodeRepository
{
    public function save(Collection $codes, int $tournamentId): void
    {
        $codesToInsert = [];

        foreach ($codes as $code) {
            $codesToInsert[] = [
                'code' => $code,
                'expires_at' => Carbon::create(2026, 1, 1, 1, 1, 1),
                'tournament_id' => $tournamentId
            ];
        }

        LoginCode::insert($codesToInsert);
    }
}











