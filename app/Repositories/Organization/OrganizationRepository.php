<?php

namespace App\Repositories\Organization;

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
        return Organization::all()->map(fn($organization) => OrganizationDomain::fromEloquent($organization));
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

    /**
     * @param int|null $id
     * @return OrganizationDomain|null
     */
    public function findByIdWithAdmins(?int $id): ?OrganizationDomain
    {
        $organization = Organization::with('admins')->findOrFail($id);
        return $organization ? OrganizationDomain::fromEloquent($organization, ['admins']) : null;
    }

    /**
     * @param string $name
     * @param string $description
     * @param int $userId
     * @return OrganizationDomain
     */
    public function create(string $name, string $description, int $userId): OrganizationDomain
    {
        $organization = Organization::create([
            'name' => $name,
            'description' => $description,
        ]);

        if(!empty($userId)) {
            $organization->admins()->attach($userId);
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
        $organization->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->admins()->attach($userId);
    }

    public function removeAdmin(int $organizationId, int $userId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $organization->admins()->detach($userId);
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
        $organization = Organization::findOrFail($organizationId);
        $organization->name = $name;
        $organization->description = $description;

        if ($matchFormatPresets !== null) {
            $organization->match_format_presets = $matchFormatPresets;
        }

        $organization->save();
    }

    /**
     * @param int $organizationId
     * @return OrganizationDomain
     */
    public function findByIdWithGuests(int $organizationId): OrganizationDomain
    {
        $organization = Organization::with('guests')->findOrFail($organizationId);
        return OrganizationDomain::fromEloquent($organization, ['guests']);
    }
}












