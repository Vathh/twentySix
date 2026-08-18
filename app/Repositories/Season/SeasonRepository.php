<?php

namespace App\Repositories\Season;

use App\Domain\SeasonDomain;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeasonRepository
{

    public const INDEX_PER_PAGE = 9;

    /**
     * @return Collection<int, SeasonDomain>
     */
    public function getAll(): Collection
    {
        return Season::query()
            ->with('organization')
            ->get()
            ->map(fn (Season $season) => SeasonDomain::fromEloquent($season, ['organization']));
    }

    /**
     * Strona listy sezonów (najpierw najnowsze daty startu).
     *
     * @return array{items: Collection<int, SeasonDomain>, has_more: bool}
     */
    public function getPage(int $page): array
    {
        $page = max(1, $page);
        $paginator = Season::query()
            ->with('organization')
            ->orderByRaw('COALESCE(start_date, end_date, ?) DESC', ['1970-01-01'])
            ->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Season $season) => SeasonDomain::fromEloquent($season, ['organization']))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function create(
        ?int     $organizationId,
        string  $name,
        array   $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null)
    : void
    {
        DB::transaction(function () use ($organizationId, $name, $adminsIds, $startDate, $endDate) {

            $season = Season::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            if (!empty($adminsIds)) {
                $season->admins()->attach($adminsIds);
            }
        });
    }

    /**
     * @param int $seasonId
     * @return Collection<int, User>
     */
    public function getRelatedUsers(int $seasonId): Collection
    {
        $season = Season::with(['organization.relatedUsers.player', 'relatedUsers.player'])->findOrFail($seasonId);
        $seasonRelatedUsers = $season->relatedUsers;
        $organizationRelatedUsers = $season->organization->relatedUsers;

        return $seasonRelatedUsers
                    ->merge($organizationRelatedUsers)
                    ->unique('id')
                    ->values();
    }

    public function addRelatedUser(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->attach($userId);
    }

    public function removeAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->detach($userId);
    }

    /**
     * @param int $seasonId
     * @return SeasonDomain
     */
    public function findByIdWithOrganizationAndGuests(int $seasonId): SeasonDomain
    {
        $season = Season::with(['organization', 'guests'])->findOrFail($seasonId);
        return SeasonDomain::fromEloquent($season, ['organization', 'guests']);
    }

    /**
     * Surowy model Eloquent (np. do autoryzacji policy).
     */
    public function findModel(int $seasonId): Season
    {
        return Season::findOrFail($seasonId);
    }
}












