<?php

namespace App\Services\Tournament;

use App\Enums\TournamentStatus;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentGuestParticipantRepository;
use App\Repositories\Tournament\TournamentInvitationRepository;

class TournamentGuestParticipantService
{
    public function __construct(
        private TournamentGuestParticipantRepository $repository,
        private PlayerRepository $playerRepository,
        private TournamentInvitationRepository $invitationRepository,
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

    /**
     * Tworzy gościa bez konta i od razu dodaje go do turnieju (np. turniej jednorazowy).
     */
    public function createAndAdd(int $tournamentId, string $name): void
    {
        $this->assertTournamentOpen($tournamentId);

        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \RuntimeException('Podaj imię gościa.');
        }

        $this->assertNameAvailableInTournament($tournamentId, $trimmed);

        $player = Player::create([
            'name' => $trimmed,
            'user_id' => null,
            'league_id' => null,
            'season_id' => null,
        ]);

        $this->repository->add($tournamentId, $player->id);
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

    private function assertNameAvailableInTournament(int $tournamentId, string $name): void
    {
        foreach ($this->repository->getPlayersForTournament($tournamentId) as $player) {
            if (strcasecmp($player->name, $name) === 0) {
                throw new \RuntimeException('Uczestnik o tej nazwie jest już w turnieju.');
            }
        }

        foreach ($this->invitationRepository->getAcceptedPlayers($tournamentId) as $player) {
            if (strcasecmp($player->name, $name) === 0) {
                throw new \RuntimeException('Uczestnik o tej nazwie jest już w turnieju.');
            }
        }
    }
}
