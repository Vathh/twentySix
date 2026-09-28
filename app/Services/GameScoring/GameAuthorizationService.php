<?php

namespace App\Services\GameScoring;

use App\Enums\GameKind;
use App\Enums\TournamentStatus;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Repositories\Tournament\TournamentRepository;
use Illuminate\Support\Facades\Auth;

class GameAuthorizationService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
    ) {}

    public function canCorrectTournamentGame(?int $tournamentId, GameKind $kind): bool
    {
        if ($kind === GameKind::QUICK || $kind === GameKind::LEAGUE || $tournamentId === null || ! Auth::check()) {
            return false;
        }

        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId);

        return $this->canManageTournament($tournament);
    }

    /**
     * Admin turnieju (pivot) albo — dla turnieju w sezonie — admin sezonu.
     * Nie używamy can_create_organizations: to tylko prawo tworzenia organizacji/turniejów.
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

    /**
     * Live scoring / lock tabletem: kod sędziowski tego turnieju, turniej nie zakończony.
     * Konto gracza (User) nie sędziuje turnieju.
     */
    public function assertLiveTournamentScoring(mixed $actor, ?int $tournamentId): void
    {
        if ($tournamentId === null || $tournamentId < 1) {
            abort(403, 'Brak uprawnień do sędziowania tego meczu.');
        }

        if (! $actor instanceof LoginCode) {
            abort(403, 'Sędziowanie turnieju wymaga kodu tabletu.');
        }

        if ((int) $actor->tournament_id !== (int) $tournamentId) {
            abort(403, 'Ten kod nie należy do tego turnieju.');
        }

        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId);
        if ($tournament === null) {
            abort(404);
        }
        if ($tournament->status === TournamentStatus::FINISHED) {
            abort(403, 'Turniej zakończony — sędziowanie jest już nieważne.');
        }
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
