<?php

namespace App\Services\Player;

use App\Repositories\Player\PlayerGameHistoryRepository;

class PlayerGameHistoryService
{
    public function __construct(
        private PlayerGameHistoryRepository $playerGameHistoryRepository
    ) {
    }

    /**
     * Zwraca stronę historii meczów gracza (5 na stronę).
     *
     * @return array{items: array, has_more: bool}
     */
    public function getHistoryPage(int $playerId, int $page): array
    {
        return $this->playerGameHistoryRepository->getHistoryPage($playerId, max(1, $page));
    }
}












