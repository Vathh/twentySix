<?php

namespace App\Services\Tournament;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameKind;
use App\Repositories\Tournament\TournamentPlayResetRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\Badge\BadgeAwardService;
use App\Services\Player\PlayerOverviewService;
use App\Services\Player\PlayerStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentCancelService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentPlayResetRepository $playResetRepository,
        private LoginCodeService $loginCodeService,
        private BadgeAwardService $badgeAwardService,
        private PlayerStatsService $playerStatsService,
        private PlayerOverviewService $playerOverviewService,
    ) {}

    public function cancel(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findModel($tournamentId);
        $domain = TournamentDomain::fromEloquent($tournament);

        if (! $domain->canCancelPlay()) {
            throw ValidationException::withMessages([
                'tournament' => $domain->isStarted()
                    ? 'Zakończonego turnieju nie można anulować.'
                    : 'Turniej jeszcze nie wystartował.',
            ]);
        }

        $groupGameIds = $this->playResetRepository->groupGameIds($tournamentId);
        $playoffGameIds = $this->playResetRepository->playoffGameIds($tournamentId);
        $playerIds = $this->playResetRepository->playerIds($tournamentId);

        foreach ($groupGameIds as $gameId) {
            $this->badgeAwardService->retractForGame(GameKind::GROUP, $gameId);
        }
        foreach ($playoffGameIds as $playoffId) {
            $this->badgeAwardService->retractForGame(GameKind::PLAYOFF, $playoffId);
        }

        DB::transaction(function () use ($tournamentId, $groupGameIds, $playoffGameIds) {
            $this->playResetRepository->wipePlayData($tournamentId, $groupGameIds, $playoffGameIds);
            $this->loginCodeService->revokeForTournament($tournamentId);
            $this->tournamentRepository->resetToUnstarted($tournamentId);
        });

        foreach ($playerIds as $playerId) {
            $this->playerStatsService->recalculateAndSave($playerId);
        }
        $this->playerOverviewService->rebuildRegistered($playerIds);
    }
}
