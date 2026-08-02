<?php

namespace App\Repositories\League;

use App\Domain\LeagueDomain;
use App\Models\League\League;
use Illuminate\Support\Collection;

class LeagueRepository
{
    public const INDEX_PER_PAGE = 9;

    /**
     * @return Collection<int, LeagueDomain>
     */
    public function getAll(): Collection
    {
        return League::all()->map(fn($league) => LeagueDomain::fromEloquent($league));
    }

    /**
     * Strona listy lig (najpierw ostatnio aktualizowane).
     *
     * @return array{items: Collection<int, LeagueDomain>, has_more: bool}
     */
    public function getPage(int $page): array
    {
        $page = max(1, $page);
        $paginator = League::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (League $league) => LeagueDomain::fromEloquent($league))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param int|null $id
     * @return LeagueDomain|null
     */
    public function findByIdWithAdmins(?int $id): ?LeagueDomain
    {
        $league = League::with('admins')->findOrFail($id);
        return $league ? LeagueDomain::fromEloquent($league, ['admins']) : null;
    }

    /**
     * @param string $name
     * @param string $description
     * @param int $userId
     * @return LeagueDomain
     */
    public function create(string $name, string $description, int $userId): LeagueDomain
    {
        $league = League::create([
            'name' => $name,
            'description' => $description,
        ]);

        if(!empty($userId)) {
            $league->admins()->attach($userId);
        }

        return LeagueDomain::fromEloquent($league);
    }

    public function getRelatedUsers(int $leagueId): Collection
    {
        return League::findOrFail($leagueId)->relatedUsers;
    }

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->admins()->attach($userId);
    }

    public function removeAdmin(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->admins()->detach($userId);
    }

    /**
     * @param  array<string, array<string, int|string>>|null  $matchFormatPresets
     */
    public function update(
        int $leagueId,
        string $name,
        string $description,
        ?array $matchFormatPresets = null,
    ): void {
        $league = League::findOrFail($leagueId);
        $league->name = $name;
        $league->description = $description;

        if ($matchFormatPresets !== null) {
            $league->match_format_presets = $matchFormatPresets;
        }

        $league->save();
    }

    /**
     * @param int $leagueId
     * @return LeagueDomain
     */
    public function findByIdWithGuests(int $leagueId): LeagueDomain
    {
        $league = League::with('guests')->findOrFail($leagueId);
        return LeagueDomain::fromEloquent($league, ['guests']);
    }
}












