<?php

namespace App\Repositories\Season;

use App\Domain\AdminRoster;
use App\Domain\SeasonDomain;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Users\User;
use App\Support\Catalog\CatalogSort;
use App\Support\Catalog\SeasonCatalogStatus;
use App\Support\Text\PolishFold;
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
     * @return array{items: Collection<int, SeasonDomain>, has_more: bool, total: int}
     */
    public function getPage(int $page, ?string $search = null, ?string $sort = null, ?string $status = null): array
    {
        $page = max(1, $page);
        $query = Season::query()
            ->with('organization')
            ->withCount('tournaments');
        $pattern = PolishFold::likePattern($search);
        if ($pattern !== null) {
            $query->where(function ($inner) use ($pattern) {
                $inner->whereRaw(PolishFold::likeSql('seasons.name'), PolishFold::likeBindings($pattern))
                    ->orWhereHas('organization', function ($organization) use ($pattern) {
                        $organization->whereRaw(PolishFold::likeSql('organizations.name'), PolishFold::likeBindings($pattern));
                    });
            });
        }
        SeasonCatalogStatus::apply($query, $status);
        if ($sort === CatalogSort::MEMBERS) {
            $query->orderByRaw('(select count(*) from organization_user where organization_user.organization_id = seasons.organization_id) DESC')
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        } elseif (! CatalogSort::apply($query, $sort, 'id')) {
            $query->orderByRaw('COALESCE(start_date, end_date, ?) DESC', ['1970-01-01'])
                ->orderByDesc('id');
        }
        $paginator = $query->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Season $season) => SeasonDomain::fromEloquent($season, ['organization']))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function create(
        ?int $organizationId,
        string $name,
        array $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null): void
    {
        DB::transaction(function () use ($organizationId, $name, $adminsIds, $startDate, $endDate) {

            $season = Season::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            if (! empty($adminsIds)) {
                $season->admins()->syncWithoutDetaching($adminsIds);
                if ($organizationId !== null) {
                    Organization::query()
                        ->findOrFail($organizationId)
                        ->relatedUsers()
                        ->syncWithoutDetaching($adminsIds);
                }
            }
        });
    }

    /**
     * Użytkownicy powiązani z organizacją sezonu — pula, z której nadaje się administratorów sezonu.
     *
     * @return Collection<int, User>
     */
    public function getRelatedUsers(int $seasonId): Collection
    {
        $season = Season::with(['organization.relatedUsers.player'])->findOrFail($seasonId);

        return $season->organization?->relatedUsers ?? collect();
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->syncWithoutDetaching([$userId]);
    }

    public function isAdmin(int $seasonId, int $userId): bool
    {
        return Season::query()
            ->findOrFail($seasonId)
            ->admins()
            ->where('users.id', $userId)
            ->exists();
    }

    public function removeAdmin(int $seasonId, int $userId): void
    {
        $season = Season::withCount('admins')->findOrFail($seasonId);
        AdminRoster::assertCanRemove((int) $season->admins_count, 'Sezon');
        $season->admins()->detach($userId);
    }

    /**
     * Surowy model Eloquent (np. do autoryzacji policy).
     *
     * @param  list<string>  $relations
     */
    public function findModel(int $seasonId, array $relations = []): Season
    {
        return Season::with($relations)->findOrFail($seasonId);
    }
}
