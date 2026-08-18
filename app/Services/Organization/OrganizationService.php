<?php

namespace App\Services\Organization;

use App\Domain\OrganizationDomain;
use App\Repositories\Organization\OrganizationRepository;
use App\Repositories\Player\PlayerRepository;
use App\Services\Player\PlayerService;
use Illuminate\Support\Collection;

class OrganizationService
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
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
        return $this->organizationRepository
            ->getAll()
            ->sortByDesc(fn (OrganizationDomain $organization) => $organization->updatedAt->getTimestamp())
            ->values();
    }

    /**
     * @return array{items: list<array{id: int, url: string, title: string, subtitle: string}>, has_more: bool}
     */
    public function getIndexPage(int $page): array
    {
        $pageData = $this->organizationRepository->getPage($page);

        return [
            'items' => $pageData['items']->map(fn (OrganizationDomain $organization) => [
                'id' => $organization->id,
                'url' => route('organizations.show', ['organization' => $organization->id]),
                'title' => $organization->displayTitle(),
                'subtitle' => $organization->getCardSubtitle(),
            ])->all(),
            'has_more' => $pageData['has_more'],
        ];
    }

    public function getByIdWithAdmins(int $id): ?OrganizationDomain
    {
        return $this->organizationRepository->findByIdWithAdmins($id);
    }

    public function create(string $name, string $description, int $userId): OrganizationDomain
    {
        return $this->organizationRepository->create($name, $description, $userId);
    }

    public function addRelatedUser(int $organizationId, int $userId): void
    {
        // Pobierz gracza użytkownika (domenowy obiekt)
        $playerDomain = $this->playerRepository->findByUserId($userId);
        
        // Pobierz organizację z gośćmi (domenowy obiekt)
        $organizationDomain = $this->organizationRepository->findByIdWithGuests($organizationId);

        // Jeśli użytkownik ma gracza (Player), sprawdź czy nie ma konfliktu z gościem
        if ($playerDomain) {
            $playerName = $playerDomain->name;

            // Sprawdź gości w organizacji
            $guestInOrganization = $this->playerService->findGuestByName($playerName, null, $organizationId);
            if ($guestInOrganization) {
                $newName = $this->playerService->generateUniqueGuestName($playerName, null, $organizationId);
                $this->playerService->updateGuestName($guestInOrganization->id, $newName);
            }
        }

        $this->organizationRepository->addRelatedUser($organizationId, $userId);
    }

    public function removeRelatedUser(int $organizationId, int $userId): void
    {
        $this->organizationRepository->removeRelatedUser($organizationId, $userId);
    }

    public function addAdmin(int $organizationId, int $userId): void
    {
        $this->organizationRepository->addAdmin($organizationId, $userId);
    }

    public function removeAdmin(int $organizationId, int $userId): void
    {
        $this->organizationRepository->removeAdmin($organizationId, $userId);
    }

    /**
     * @param  array<string, array<string, int|string>>|null  $matchFormatPresets
     */
    public function update(
        int $organizationId,
        string $name,
        string $description,
        ?array $matchFormatPresets = null,
    ): void {
        $this->organizationRepository->update($organizationId, $name, $description, $matchFormatPresets);
    }
}












