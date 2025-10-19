<?php
namespace App\Services;

use App\Domain\LeagueDomain;
use App\Domain\SeasonDomain;
use App\Models\League;
use App\Repositories\SeasonRepository;

class SeasonService
{

    public function __construct(private SeasonRepository $seasonRepository)
    {
    }

    public function create(int $leagueId,
                           string $name,
                           array $adminsIds = [],
                           ?string $startDate = null,
                           ?string $endDate = null): SeasonDomain
    {
        $league = LeagueDomain::fromEloquentWithAdmins(League::findOrFail($leagueId));
        $leagueAdminsIds = $league->getAdminsIds();
        $allAdminsIds = array_unique(array_merge($leagueAdminsIds, $adminsIds));
        return $this->seasonRepository->create($leagueId, $name, $allAdminsIds, $startDate, $endDate);
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $this->seasonRepository->addAdmin($seasonId, $userId);
    }
}
