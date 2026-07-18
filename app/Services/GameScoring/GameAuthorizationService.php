<?php

namespace App\Services\GameScoring;

use App\Enums\GameKind;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Facades\Auth;

class GameAuthorizationService
{
    public function canCorrectTournamentGame(?int $tournamentId, GameKind $kind): bool
    {
        if ($kind === GameKind::QUICK || $tournamentId === null || ! Auth::check()) {
            return false;
        }

        $tournament = Tournament::query()->find($tournamentId);

        return $this->canManageTournament($tournament);
    }

    /**
     * Admin turnieju (pivot) albo — dla turnieju w sezonie — admin sezonu.
     * Nie używamy can_create_leagues: to tylko prawo tworzenia lig/turniejów.
     */
    public function canManageTournament(?Tournament $tournament): bool
    {
        if ($tournament === null || ! Auth::check()) {
            return false;
        }

        $tournament->loadMissing(['admins', 'season.admins']);
        $userId = Auth::id();

        if ($tournament->admins->contains('id', $userId)) {
            return true;
        }

        if ($tournament->season !== null) {
            return $tournament->season->admins->contains('id', $userId);
        }

        return false;
    }

    public function authorizeTournamentGame(?int $tournamentId, GameKind $kind): void
    {
        if (! $this->canCorrectTournamentGame($tournamentId, $kind)) {
            abort(403, 'Brak uprawnień do edycji wyniku tego meczu.');
        }
    }

    public function authorizeManageTournament(Tournament $tournament): void
    {
        if (! $this->canManageTournament($tournament)) {
            abort(403, 'Brak uprawnień do zarządzania tym turniejem.');
        }
    }
}
