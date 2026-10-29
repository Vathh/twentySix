<?php

namespace App\Services\Season;

use App\Domain\SeasonDomain;
use App\Models\Player\Player;
use App\Repositories\Organization\OrganizationRepository;
use App\Repositories\Season\SeasonRepository;
use App\Services\Organization\OrganizationService;
use App\Support\Catalog\SeasonCatalogStatus;
use App\Support\Text\PolishFold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeasonService
{
    public function __construct(
        private SeasonRepository $seasonRepository,
        private OrganizationRepository $organizationRepository,
        private OrganizationService $organizationService,
    ) {}

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
     * @return array{items: list<array<string, mixed>>, has_more: bool, total: int, summary: string|null}
     */
    public function getIndexPage(int $page, ?string $search = null, ?string $sort = null, ?string $status = null): array
    {
        $pageData = $this->seasonRepository->getPage($page, $search, $sort, $status);

        return [
            'items' => $pageData['items']->map(function (SeasonDomain $season) {
                $dates = $season->getPlayDatesFormatted();
                $meta = array_values(array_filter([
                    $dates ?? 'Data nieustawiona',
                    $season->tournamentCountLabel(),
                ]));
                $phase = SeasonCatalogStatus::phase($season->startDate, $season->endDate);

                return [
                    'id' => $season->id,
                    'url' => route('seasons.show', ['season' => $season->id]),
                    'title' => $season->displayTitle(),
                    'name' => $season->name,
                    'logoUrl' => $season->organization?->logoUrl(),
                    'initials' => $season->organization !== null
                        ? Player::initialsFromName($season->organization->name)
                        : null,
                    'context' => $season->organization?->name,
                    'description' => null,
                    'meta' => implode(' · ', $meta),
                    'subtitle' => $dates ? 'Data rozgrywek: '.$dates : null,
                    'subtitle_missing' => $dates === null,
                    'status_label' => SeasonCatalogStatus::label($phase),
                    'status_variant' => $phase,
                ];
            })->all(),
            'has_more' => $pageData['has_more'],
            'total' => $pageData['total'],
            'summary' => PolishFold::matchSummary($pageData['total'], $search, 'sezon', 'sezony', 'sezonów'),
        ];
    }

    public function create(
        ?int $organizationId,
        string $name,
        array $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null
    ): void {
        $organization = $this->organizationRepository->findByIdWithAdmins($organizationId);
        $organizationAdminsIds = $organization?->getAdminsIds() ?? [];
        $allAdminsIds = array_unique(array_merge($organizationAdminsIds, $adminsIds));
        try {
            $this->seasonRepository->create($organizationId, $name, $allAdminsIds, $startDate, $endDate);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'general' => 'Nie udało się dodać sezonu. Spróbuj ponownie.',
            ]);
        }
    }

    /**
     * @param  list<string>  $additionalRelations
     */
    public function loadAndAuthorize(int $seasonId, array $additionalRelations = []): SeasonDomain
    {
        $allRelations = array_merge($additionalRelations, ['admins']);
        $season = $this->seasonRepository->findModel($seasonId, array_values(array_unique($allRelations)));
        Gate::authorize('update', $season);

        return SeasonDomain::fromEloquent($season, $allRelations);
    }

    public function getRelatedUsers(int $seasonId): Collection
    {
        return $this->seasonRepository->getRelatedUsers($seasonId);
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $season = $this->seasonRepository->findModel($seasonId);
        if ($season->organization_id !== null) {
            $this->organizationService->addRelatedUser((int) $season->organization_id, $userId);
        }

        $this->seasonRepository->addAdmin($seasonId, $userId);
    }

    public function removeAdmin(int $seasonId, int $userId): void
    {
        $this->seasonRepository->removeAdmin($seasonId, $userId);
    }
}
