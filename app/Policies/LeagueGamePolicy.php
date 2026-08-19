<?php

namespace App\Policies;

use App\Models\League\LeagueGame;
use App\Models\Users\User;

class LeagueGamePolicy
{
    public function view(?User $user, LeagueGame $leagueGame): bool
    {
        return true;
    }

    public function update(User $user, LeagueGame $leagueGame): bool
    {
        return $leagueGame->season->league->organization->admins->contains('id', $user->id);
    }

    public function play(User $user, LeagueGame $leagueGame): bool
    {
        $playerId = $user->player?->id;

        return $playerId !== null
            && in_array($playerId, [(int) $leagueGame->player1_id, (int) $leagueGame->player2_id], true);
    }

    public function score(User $user, LeagueGame $leagueGame): bool
    {
        return $this->play($user, $leagueGame)
            && (int) $leagueGame->scoring_host_player_id === (int) $user->player?->id;
    }
}
