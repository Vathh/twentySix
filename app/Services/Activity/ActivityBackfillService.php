<?php

namespace App\Services\Activity;

use App\Repositories\Game\GameVisitRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaVisitRepository;
use App\Repositories\Tournament\TournamentRepository;
use Carbon\Carbon;

class ActivityBackfillService
{
    public function __construct(
        private TournamentRepository $tournaments,
        private ActivityRecorder $recorder,
        private GameVisitRepository $gameVisits,
        private QuickGameFfaVisitRepository $ffaVisits,
        private PlayerRepository $players,
    ) {}

    /**
     * @return array{tournaments: int, first180: int}
     */
    public function run(): array
    {
        $tournaments = 0;
        foreach ($this->tournaments->finishedIds() as $tournamentId) {
            $this->recorder->tournamentChampion($tournamentId);
            $tournaments++;
        }

        $first180 = 0;
        foreach ($this->earliest180s() as $playerId => $occurredAt) {
            $player = $this->players->findById($playerId);
            if ($player === null || $player->userId === null) {
                continue;
            }

            $this->recorder->playerFirst180($playerId, Carbon::parse($occurredAt));
            $first180++;
        }

        return ['tournaments' => $tournaments, 'first180' => $first180];
    }

    /**
     * @return array<int, string>
     */
    private function earliest180s(): array
    {
        $merged = $this->gameVisits->earliest180AtByPlayer();

        foreach ($this->ffaVisits->earliest180AtByPlayer() as $playerId => $occurredAt) {
            if (! isset($merged[$playerId]) || $occurredAt < $merged[$playerId]) {
                $merged[$playerId] = $occurredAt;
            }
        }

        return $merged;
    }
}
