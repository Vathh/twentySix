<?php

namespace App\Repositories\Organization;

use App\Domain\AdminRoster;
use App\Domain\OrganizationDomain;
use App\Models\Organization\Organization;
use Illuminate\Support\Collection;

class OrganizationRepository
{
    public const INDEX_PER_PAGE = 9;

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
     * @return array{items: Collection<int, OrganizationDomain>, has_more: bool}
     */
    public function getPage(int $page): array
    {
        $page = max(1, $page);
        $paginator = Organization::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Organization $organization) => OrganizationDomain::fromEloquent($organization))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
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
