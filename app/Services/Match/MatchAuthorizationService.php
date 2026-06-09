<?php

namespace App\Services\Match;

use App\Enums\MatchKind;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Facades\Auth;

class MatchAuthorizationService
{
    public function canCorrectTournamentMatch(?int $tournamentId, MatchKind $kind): bool
    {
        if ($kind === MatchKind::QUICK || $tournamentId === null || ! Auth::check()) {
            return false;
        }

        $tournament = Tournament::with('season.admins')->find($tournamentId);

        if ($tournament?->season === null) {
            return false;
        }

        return $this->userCanManageSeason($tournament->season);
    }

    public function authorizeTournamentMatch(?int $tournamentId, MatchKind $kind): void
    {
        if (! $this->canCorrectTournamentMatch($tournamentId, $kind)) {
            abort(403, 'Brak uprawnień do edycji wyniku tego meczu.');
        }
    }

    private function userCanManageSeason(Season $season): bool
    {
        return $season->admins->contains('id', Auth::id());
    }
}
