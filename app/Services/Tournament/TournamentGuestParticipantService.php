<?php

namespace App\Services\Tournament;

use App\Enums\TournamentStatus;
use App\Models\Tournament\Tournament;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentGuestParticipantRepository;

class TournamentGuestParticipantService
{
    public function __construct(
        private TournamentGuestParticipantRepository $repository,
        private PlayerRepository $playerRepository,
    ) {
    }

    public function addFromRelatedPool(int $tournamentId, int $playerId, int $seasonId): void
    {
        $this->assertTournamentOpen($tournamentId);

        $relatedGuestIds = $this->playerRepository
            ->getSeasonGuests($seasonId)
            ->pluck('id');

        if (! $relatedGuestIds->contains($playerId)) {
            throw new \RuntimeException('Gość nie należy do puli powiązanych gości ligi/sezonu');
        }

        $this->repository->add($tournamentId, $playerId);
    }

    public function remove(int $tournamentId, int $playerId): void
    {
        $this->assertTournamentOpen($tournamentId);
        $this->repository->remove($tournamentId, $playerId);
    }

    private function assertTournamentOpen(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if ($tournament->status !== TournamentStatus::CREATED) {
            throw new \RuntimeException('Turniej już wystartował — nie można zmieniać uczestników');
        }
    }
}
