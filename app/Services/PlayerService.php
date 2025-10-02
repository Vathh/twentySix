<?php
namespace App\Services;

use App\Domain\PlayerDomain;
use App\Repositories\PlayerRepository;

class PlayerService
{
    public function __construct(private PlayerRepository $playerRepository)
    {}

    public function create(string $name, int $userId): PlayerDomain
    {
        return $this->playerRepository->create($name, $userId);
    }
}
