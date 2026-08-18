<?php

namespace App\Services\Player;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Models\Player\Player;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentGuestParticipantRepository;
use App\Repositories\Tournament\TournamentInvitationRepository;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\error;

class PlayerService
{
    public function __construct(
        private PlayerRepository $playerRepository,
        private TournamentInvitationRepository $tournamentInvitationRepository,
        private TournamentGuestParticipantRepository $tournamentGuestParticipantRepository,
    ) {
    }

    public function create(string $name, int $userId): void
    {
        $this->playerRepository->create($name, $userId);
    }

    /**
     * @return Collection<int, Player>
     */
    public function searchRegisteredByName(string $query): Collection
    {
        $nameQuery = trim($query);
        if ($nameQuery === '') {
            return collect();
        }

        return $this->playerRepository->searchRegisteredByName($nameQuery);
    }

    public function createGuest(string $name, int $targetId, AssignableEntityType $targetType): void
    {
        try {
            $this->playerRepository->createGuest($name, $targetId, $targetType);
        } catch (Throwable $e) {
            throw new RuntimeException('Nie udało się dodać gracza', 0, $e);
        }
    }

    public function removeGuest(int $playerId): void
    {
        $this->playerRepository->removeGuest($playerId);
    }

    public function getRelatedPlayers(int $seasonId): Collection
    {
        try {
            return $this->playerRepository->getRelatedPlayers($seasonId);
        } catch (Throwable $e) {
            return collect();
        }
    }

    /**
     * @return Collection<int, PlayerDomain>
     */
    public function getRelatedRegisteredUsers(int $seasonId): Collection
    {
        return $this->playerRepository->getRelatedRegisteredUsers($seasonId);
    }

    /**
     * @return Collection<int, PlayerDomain>
     */
    public function getSeasonGuests(int $seasonId): Collection
    {
        return $this->playerRepository->getSeasonGuests($seasonId);
    }

    /**
     * @return Collection<int, PlayerDomain>
     */
    public function getTournamentGuestParticipants(int $tournamentId): Collection
    {
        return $this->tournamentGuestParticipantRepository->getPlayersForTournament($tournamentId);
    }

    /**
     * Uczestnicy turnieju: zaakceptowane zaproszenia + goście dodani do turnieju.
     *
     * @return Collection<int, PlayerDomain>
     */
    public function getTournamentStartPool(int $tournamentId): Collection
    {
        $accepted = $this->tournamentInvitationRepository->getAcceptedPlayers($tournamentId);
        $guests = $this->tournamentGuestParticipantRepository->getPlayersForTournament($tournamentId);

        // toBase(): pusta Eloquent Collection po map(Domain) zostaje Eloquent,
        // a Eloquent::merge() woła getKey() — PlayerDomain go nie ma.
        return $accepted
            ->toBase()
            ->merge($guests->toBase())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Zmienia nazwę gościa, aby uniknąć konfliktu z zarejestrowanym graczem
     * @param int $playerId
     * @param string $newName
     * @return void
     */
    public function updateGuestName(int $playerId, string $newName): void
    {
        $this->playerRepository->updateGuestName($playerId, $newName);
    }

    /**
     * Znajduje gościa o danej nazwie w sezonie lub organizacji
     * @param string $name
     * @param int|null $seasonId
     * @param int|null $organizationId
     * @return PlayerDomain|null
     */
    public function findGuestByName(string $name, ?int $seasonId = null, ?int $organizationId = null): ?PlayerDomain
    {
        return $this->playerRepository->findGuestByName($name, $seasonId, $organizationId);
    }

    /**
     * Generuje unikalną nazwę dla gościa
     * Format: "Tomek", "Tomek 1", "Tomek 2", itd.
     * @param string $baseName
     * @param int|null $seasonId
     * @param int|null $organizationId
     * @return string
     */
    public function generateUniqueGuestName(string $baseName, ?int $seasonId = null, ?int $organizationId = null): string
    {
        return $this->playerRepository->generateUniqueGuestName($baseName, $seasonId, $organizationId);
    }
}












