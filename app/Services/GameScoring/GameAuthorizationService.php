<?php

namespace App\Services\GameScoring;

use App\Enums\GameKind;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Facades\Auth;

class GameAuthorizationService
{
    public function canCorrectTournamentGame(?int $tournamentId, GameKind $kind): bool
    {
        if ($kind === GameKind::QUICK || $tournamentId === null || ! Auth::check()) {
            return false;
        }

        $tournament = Tournament::with('season.admins')->find($tournamentId);

        if ($tournament?->season === null) {
            return false;
        }

        return $this->userCanManageSeason($tournament->season);
    }

    public function authorizeTournamentGame(?int $tournamentId, GameKind $kind): void
    {
        if (! $this->canCorrectTournamentGame($tournamentId, $kind)) {
            abort(403, 'Brak uprawnień do edycji wyniku tego meczu.');
        }
    }

    private function userCanManageSeason(Season $season): bool
    {
        return $season->admins->contains('id', Auth::id());
    }
}
