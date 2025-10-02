<?php
namespace App\Services;

use App\Domain\SeasonDomain;
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
        return $this->seasonRepository->create($leagueId, $name, $adminsIds, $startDate, $endDate);
    }
}
