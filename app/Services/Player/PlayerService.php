<?php

namespace App\Services\Player;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Repositories\Player\PlayerRepository;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\error;

class PlayerService
{
    public function __construct(private PlayerRepository $playerRepository)
    {
    }

    public function create(string $name, int $userId): void
    {
        $this->playerRepository->create($name, $userId);
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
     * Znajduje gościa o danej nazwie w sezonie lub lidze
     * @param string $name
     * @param int|null $seasonId
     * @param int|null $leagueId
     * @return PlayerDomain|null
     */
    public function findGuestByName(string $name, ?int $seasonId = null, ?int $leagueId = null): ?PlayerDomain
    {
        return $this->playerRepository->findGuestByName($name, $seasonId, $leagueId);
    }

    /**
     * Generuje unikalną nazwę dla gościa
     * Format: "Tomek", "Tomek 1", "Tomek 2", itd.
     * @param string $baseName
     * @param int|null $seasonId
     * @param int|null $leagueId
     * @return string
     */
    public function generateUniqueGuestName(string $baseName, ?int $seasonId = null, ?int $leagueId = null): string
    {
        return $this->playerRepository->generateUniqueGuestName($baseName, $seasonId, $leagueId);
    }
}











