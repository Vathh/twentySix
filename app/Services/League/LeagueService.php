<?php

namespace App\Services\League;

use App\Domain\LeagueDomain;
use App\Repositories\League\LeagueRepository;
use App\Repositories\Player\PlayerRepository;
use App\Services\Player\PlayerService;
use Illuminate\Support\Collection;

class LeagueService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private PlayerService $playerService,
        private PlayerRepository $playerRepository
    )
    {
    }

    /**
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->leagueRepository
            ->getAll()
            ->sortByDesc(fn (LeagueDomain $league) => $league->updatedAt->getTimestamp())
            ->values();
    }

    /**
     * @return array{items: list<array{id: int, url: string, title: string, subtitle: string}>, has_more: bool}
     */
    public function getIndexPage(int $page): array
    {
        $pageData = $this->leagueRepository->getPage($page);

        return [
            'items' => $pageData['items']->map(fn (LeagueDomain $league) => [
                'id' => $league->id,
                'url' => route('leagues.show', ['league' => $league->id]),
                'title' => $league->displayTitle(),
                'subtitle' => $league->getCardSubtitle(),
            ])->all(),
            'has_more' => $pageData['has_more'],
        ];
    }

    public function getByIdWithAdmins(int $id): ?LeagueDomain
    {
        return $this->leagueRepository->findByIdWithAdmins($id);
    }

    public function create(string $name, string $description, int $userId): LeagueDomain
    {
        return $this->leagueRepository->create($name, $description, $userId);
    }

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        // Pobierz gracza użytkownika (domenowy obiekt)
        $playerDomain = $this->playerRepository->findByUserId($userId);
        
        // Pobierz ligę z gośćmi (domenowy obiekt)
        $leagueDomain = $this->leagueRepository->findByIdWithGuests($leagueId);

        // Jeśli użytkownik ma gracza (Player), sprawdź czy nie ma konfliktu z gościem
        if ($playerDomain) {
            $playerName = $playerDomain->name;

            // Sprawdź gości w lidze
            $guestInLeague = $this->playerService->findGuestByName($playerName, null, $leagueId);
            if ($guestInLeague) {
                $newName = $this->playerService->generateUniqueGuestName($playerName, null, $leagueId);
                $this->playerService->updateGuestName($guestInLeague->id, $newName);
            }
        }

        $this->leagueRepository->addRelatedUser($leagueId, $userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $this->leagueRepository->removeRelatedUser($leagueId, $userId);
    }

    public function addAdmin(int $leagueId, int $userId): void
    {
        $this->leagueRepository->addAdmin($leagueId, $userId);
    }

    public function removeAdmin(int $leagueId, int $userId): void
    {
        $this->leagueRepository->removeAdmin($leagueId, $userId);
    }

    /**
     * @param  array<string, array<string, int|string>>|null  $matchFormatPresets
     */
    public function update(
        int $leagueId,
        string $name,
        string $description,
        ?array $matchFormatPresets = null,
    ): void {
        $this->leagueRepository->update($leagueId, $name, $description, $matchFormatPresets);
    }
}












