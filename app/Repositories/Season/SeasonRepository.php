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
            ->with('league')
            ->get()
            ->map(fn (Season $season) => SeasonDomain::fromEloquent($season, ['league']));
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
            ->with('league')
            ->orderByRaw('COALESCE(start_date, end_date, ?) DESC', ['1970-01-01'])
            ->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Season $season) => SeasonDomain::fromEloquent($season, ['league']))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function create(
        ?int     $leagueId,
        string  $name,
        array   $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null)
    : void
    {
        DB::transaction(function () use ($leagueId, $name, $adminsIds, $startDate, $endDate) {

            $season = Season::create([
                'league_id' => $leagueId,
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
        $season = Season::with(['league.relatedUsers.player', 'relatedUsers.player'])->findOrFail($seasonId);
        $seasonRelatedUsers = $season->relatedUsers;
        $leagueRelatedUsers = $season->league->relatedUsers;

        return $seasonRelatedUsers
                    ->merge($leagueRelatedUsers)
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
    public function findByIdWithLeagueAndGuests(int $seasonId): SeasonDomain
    {
        $season = Season::with(['league', 'guests'])->findOrFail($seasonId);
        return SeasonDomain::fromEloquent($season, ['league', 'guests']);
    }
}












