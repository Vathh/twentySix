<?php

namespace App\Repositories\Organization;

use App\Domain\Activity\FollowSubject;
use App\Domain\AdminRoster;
use App\Domain\OrganizationDomain;
use App\Models\Organization\Organization;
use App\Repositories\Activity\FollowRepository;
use App\Support\Catalog\CatalogSort;
use App\Support\Text\PolishFold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRepository
{
    public const INDEX_PER_PAGE = 9;

    public function __construct(private FollowRepository $follows) {}

    /**
     * @return Collection<int, OrganizationDomain>
     */
    public function getAll(): Collection
    {
        return Organization::all()->map(fn ($organization) => OrganizationDomain::fromEloquent($organization));
    }

    /**
     * Strona listy organizacji (najpierw ostatnio aktualizowane).
     *
     * @return array{items: Collection<int, OrganizationDomain>, has_more: bool, total: int}
     */
    public function getPage(int $page, ?string $search = null, ?string $sort = null): array
    {
        $page = max(1, $page);
        $query = Organization::query()
            ->withCount(['seasons', 'tournaments', 'oneOffTournaments', 'relatedUsers']);
        PolishFold::restrict($query, 'organizations.name', $search);
        if (! CatalogSort::apply($query, $sort, 'related_users_count')) {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        }
        $paginator = $query->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Organization $organization) => OrganizationDomain::fromEloquent($organization))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ];
    }

    public function findByIdWithAdmins(?int $id): ?OrganizationDomain
    {
        $organization = Organization::with('admins')->findOrFail($id);

        return $organization ? OrganizationDomain::fromEloquent($organization, ['admins']) : null;
    }

    public function create(string $name, ?string $description, int $userId): OrganizationDomain
    {
        $organization = Organization::create([
            'name' => $name,
            'description' => self::normalizeDescription($description),
        ]);

        if (! empty($userId)) {
            $organization->admins()->syncWithoutDetaching([$userId]);
            $organization->relatedUsers()->syncWithoutDetaching([$userId]);
            $this->follows->follow($userId, FollowSubject::Organization, (int) $organization->id);
        }

        return OrganizationDomain::fromEloquent($organization);
    }

    public function getRelatedUsers(int $organizationId): Collection
    {
        return Organization::findOrFail($organizationId)->relatedUsers;
    }

    public function addRelatedUser(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->relatedUsers()->syncWithoutDetaching([$userId]);
        $this->follows->follow($userId, FollowSubject::Organization, $organizationId);
    }

    public function removeRelatedUser(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->admins()->syncWithoutDetaching([$userId]);
        $organization->relatedUsers()->syncWithoutDetaching([$userId]);
        $this->follows->follow($userId, FollowSubject::Organization, $organizationId);
    }

    /**
     * Organizacje, w których ta osoba jest jedynym administratorem.
     *
     * @return list<string>
     */
    public function soleAdminOrganizationNames(int $userId): array
    {
        return Organization::query()
            ->whereHas('admins', fn ($query) => $query->where('users.id', $userId))
            ->withCount('admins')
            ->having('admins_count', '=', 1)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function relatedOrganizationIds(int $userId): array
    {
        return DB::table('organization_user')
            ->where('user_id', $userId)
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function relatedIdsAmong(int $organizationId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isRelated(int $organizationId, int $userId): bool
    {
        return Organization::query()
            ->findOrFail($organizationId)
            ->relatedUsers()
            ->where('users.id', $userId)
            ->exists();
    }

    public function isAdmin(int $organizationId, int $userId): bool
    {
        return Organization::query()
            ->findOrFail($organizationId)
            ->admins()
            ->where('users.id', $userId)
            ->exists();
    }

    public function removeAdmin(int $organizationId, int $userId): void
    {
        $organization = Organization::withCount('admins')->findOrFail($organizationId);
        AdminRoster::assertCanRemove((int) $organization->admins_count, 'Organizacja');
        $organization->admins()->detach($userId);
    }

    /**
     * @param  array<string, array<string, int|string>>|null  $matchFormatPresets
     */
    public function update(
        int $organizationId,
        string $name,
        ?string $description,
        ?array $matchFormatPresets = null,
    ): void {
        $organization = Organization::findOrFail($organizationId);
        $organization->name = $name;
        $organization->description = self::normalizeDescription($description);

        if ($matchFormatPresets !== null) {
            $organization->match_format_presets = $matchFormatPresets;
        }

        $organization->save();
    }

    public function updateLogoPath(Organization $organization, ?string $path): Organization
    {
        $organization->logo_path = $path;
        $organization->save();

        return $organization;
    }

    public function findByIdWithGuests(int $organizationId): OrganizationDomain
    {
        $organization = Organization::with('guests')->findOrFail($organizationId);

        return OrganizationDomain::fromEloquent($organization, ['guests']);
    }

    /**
     * Surowy model Eloquent (np. do autoryzacji policy).
     *
     * @param  list<string>  $relations
     */
    public function findModel(int $organizationId, array $relations = []): Organization
    {
        return Organization::with($relations)->findOrFail($organizationId);
    }

    private static function normalizeDescription(?string $description): ?string
    {
        $trimmed = trim((string) $description);

        return $trimmed === '' ? null : $trimmed;
    }
}
