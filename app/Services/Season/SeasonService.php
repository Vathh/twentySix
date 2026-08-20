<?php
namespace App\Services\Season;

use App\Domain\SeasonDomain;
use App\Repositories\Organization\OrganizationRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Season\SeasonRepository;
use App\Services\Player\PlayerService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeasonService
{

    public function __construct(
        private SeasonRepository $seasonRepository,
        private PlayerService $playerService,
        private PlayerRepository $playerRepository,
        private OrganizationRepository $organizationRepository,
    )
    {
    }

    public function getAll(): Collection
    {
        return $this->seasonRepository->getAll()
            ->sortByDesc(function ($season) {
                $ts = $season->startDate?->getTimestamp()
                    ?? $season->endDate?->getTimestamp();

                return $ts ?? PHP_INT_MIN;
            })
            ->values();
    }

    /**
     * @return array{items: list<array{id: int, url: string, title: string, subtitle: string|null, subtitle_missing: bool}>, has_more: bool}
     */
    public function getIndexPage(int $page): array
    {
        $pageData = $this->seasonRepository->getPage($page);

        return [
            'items' => $pageData['items']->map(function (SeasonDomain $season) {
                $dates = $season->getPlayDatesFormatted();

                return [
                    'id' => $season->id,
                    'url' => route('seasons.show', ['season' => $season->id]),
                    'title' => $season->displayTitle(),
                    'subtitle' => $dates ? 'Data rozgrywek: '.$dates : null,
                    'subtitle_missing' => $dates === null,
                ];
            })->all(),
            'has_more' => $pageData['has_more'],
        ];
    }

    public function create(
        ?int     $organizationId,
        string  $name,
        array   $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null
    ): void
    {
        $organization = $this->organizationRepository->findByIdWithAdmins($organizationId);
        $organizationAdminsIds = $organization?->getAdminsIds() ?? [];
        $allAdminsIds = array_unique(array_merge($organizationAdminsIds, $adminsIds));
        try {
            $this->seasonRepository->create($organizationId, $name, $allAdminsIds, $startDate, $endDate);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'general' => 'Nie udało się dodać sezonu. Spróbuj ponownie.'
            ]);
        }
    }
    public function getRelatedUsers(int $seasonId): Collection
    {
        return $this->seasonRepository->getRelatedUsers($seasonId);
    }

    public function addRelatedUser(int $seasonId, int $userId): array
    {
        // Pobierz gracza użytkownika (domenowy obiekt)
        $playerDomain = $this->playerRepository->findByUserId($userId);
        
        // Pobierz sezon z organizacją i gośćmi (domenowy obiekt)
        $seasonDomain = $this->seasonRepository->findByIdWithOrganizationAndGuests($seasonId);

        // Jeśli użytkownik ma gracza (Player), sprawdź czy nie ma konfliktu z gościem
        if ($playerDomain) {
            $playerName = $playerDomain->name;

            // Sprawdź gości w sezonie
            $guestInSeason = $this->playerService->findGuestByName($playerName, $seasonId, null);
            if ($guestInSeason) {
                $newName = $this->playerService->generateUniqueGuestName($playerName, $seasonId, null);
                $this->playerService->updateGuestName($guestInSeason->id, $newName);
            }

            // Sprawdź gości w organizacji
            if ($seasonDomain->organization) {
                $guestInOrganization = $this->playerService->findGuestByName($playerName, null, $seasonDomain->organization->id);
                if ($guestInOrganization) {
                    $newName = $this->playerService->generateUniqueGuestName($playerName, null, $seasonDomain->organization->id);
                    $this->playerService->updateGuestName($guestInOrganization->id, $newName);
                }
            }
        }

        $this->seasonRepository->addRelatedUser($seasonId, $userId);

        return [
            'id' => $userId,
            'name' => $playerDomain?->name ?? '—',
        ];
    }

    public function removeRelatedUser(int $seasonId, int $userId): void
    {
        $this->seasonRepository->removeRelatedUser($seasonId, $userId);
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $this->seasonRepository->addAdmin($seasonId, $userId);
    }

    public function removeAdmin(int $seasonId, int $userId): void
    {
        $this->seasonRepository->removeAdmin($seasonId, $userId);
    }

}












