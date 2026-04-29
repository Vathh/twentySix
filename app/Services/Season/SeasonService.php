<?php
namespace App\Services\Season;

use App\Domain\LeagueDomain;
use App\Domain\SeasonDomain;
use App\Models\League;
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
        private PlayerRepository $playerRepository
    )
    {
    }

    public function getAll(): Collection
    {
        return $this->seasonRepository->getAll()
                                        ->sortByDesc(fn($season) => $season->updatedAt)
                                        ->values();
    }

    public function create(
        ?int     $leagueId,
        string  $name,
        array   $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null
    ): void
    {
        $league = LeagueDomain::fromEloquent(League::findOrFail($leagueId), ['admins']);
        $leagueAdminsIds = $league->getAdminsIds();
        $allAdminsIds = array_unique(array_merge($leagueAdminsIds, $adminsIds));
        try {
            $this->seasonRepository->create($leagueId, $name, $allAdminsIds, $startDate, $endDate);
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

    public function addRelatedUser(int $seasonId, int $userId): void
    {
        // Pobierz gracza użytkownika (domenowy obiekt)
        $playerDomain = $this->playerRepository->findByUserId($userId);
        
        // Pobierz sezon z ligą i gośćmi (domenowy obiekt)
        $seasonDomain = $this->seasonRepository->findByIdWithLeagueAndGuests($seasonId);

        // Jeśli użytkownik ma gracza (Player), sprawdź czy nie ma konfliktu z gościem
        if ($playerDomain) {
            $playerName = $playerDomain->name;

            // Sprawdź gości w sezonie
            $guestInSeason = $this->playerService->findGuestByName($playerName, $seasonId, null);
            if ($guestInSeason) {
                $newName = $this->playerService->generateUniqueGuestName($playerName, $seasonId, null);
                $this->playerService->updateGuestName($guestInSeason->id, $newName);
            }

            // Sprawdź gości w lidze
            if ($seasonDomain->league) {
                $guestInLeague = $this->playerService->findGuestByName($playerName, null, $seasonDomain->league->id);
                if ($guestInLeague) {
                    $newName = $this->playerService->generateUniqueGuestName($playerName, null, $seasonDomain->league->id);
                    $this->playerService->updateGuestName($guestInLeague->id, $newName);
                }
            }
        }

        $this->seasonRepository->addRelatedUser($seasonId, $userId);
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











