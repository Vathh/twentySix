<?php

namespace App\Services;

use App\Domain\PlayerDomain;
use App\Enums\AssignableEntityType;
use App\Repositories\PlayerRepository;
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

    /**
     * @throws Throwable
     */
    public function getRelatedPlayers(int $seasonId): Collection
    {
        return $this->playerRepository->getRelatedPlayers($seasonId);
    }
}
