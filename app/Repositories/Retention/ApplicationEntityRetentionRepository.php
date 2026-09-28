<?php

namespace App\Repositories\Retention;

use App\Domain\Retention\ApplicationEntityKind;
use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplicationEntityRetentionRepository
{
    public function hideRoot(ApplicationEntityKind $kind, int $id, int $userId): void
    {
        $updated = $kind->modelClass()::query()->whereKey($id)->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $userId,
            'cascade_from_type' => null,
            'cascade_from_id' => null,
        ]);

        if ($updated === 0) {
            throw new DomainException('Ten byt jest już usunięty.');
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function hideAsCascade(ApplicationEntityKind $kind, array $ids, ApplicationEntityKind $rootKind, int $rootId): void
    {
        if ($ids === []) {
            return;
        }

        $kind->modelClass()::query()->whereIn('id', $ids)->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => null,
            'cascade_from_type' => $rootKind->value,
            'cascade_from_id' => $rootId,
        ]);
    }

    /**
     * Żywe byty, które schodzą razem z tym korzeniem.
     *
     * @return list<array{0: ApplicationEntityKind, 1: list<int>}>
     */
    public function liveDescendants(ApplicationEntityKind $kind, int $id): array
    {
        return match ($kind) {
            ApplicationEntityKind::Organization => $this->liveUnderOrganization($id),
            ApplicationEntityKind::Season => [[
                ApplicationEntityKind::Tournament,
                Tournament::query()->where('season_id', $id)->pluck('id')->all(),
            ]],
            ApplicationEntityKind::League => [[
                ApplicationEntityKind::LeagueSeason,
                LeagueSeason::query()->where('league_id', $id)->pluck('id')->all(),
            ]],
            ApplicationEntityKind::Tournament, ApplicationEntityKind::LeagueSeason => [],
        };
    }

    public function restoreRoot(ApplicationEntityKind $kind, int $id): string
    {
        $root = $kind->modelClass()::onlyTrashed()
            ->whereKey($id)
            ->whereNull('cascade_from_type')
            ->first();

        if ($root === null) {
            throw new DomainException('Przywrócić można tylko byt usunięty bezpośrednio.');
        }

        $name = (string) $root->name;
        $this->clearDeletion($kind, [$id]);

        foreach (ApplicationEntityKind::cases() as $childKind) {
            $childIds = $childKind->modelClass()::onlyTrashed()
                ->where('cascade_from_type', $kind->value)
                ->where('cascade_from_id', $id)
                ->pluck('id')
                ->all();
            $this->clearDeletion($childKind, $childIds);
        }

        return $name;
    }

    /**
     * @return list<array{kind: ApplicationEntityKind, id: int, name: string, deletedAt: CarbonInterface, deletedByUserId: ?int}>
     */
    public function deletedRoots(): array
    {
        $rows = [];

        foreach (ApplicationEntityKind::cases() as $kind) {
            $models = $kind->modelClass()::onlyTrashed()
                ->whereNull('cascade_from_type')
                ->orderByDesc('deleted_at')
                ->get(['id', 'name', 'deleted_at', 'deleted_by_user_id']);

            foreach ($models as $model) {
                $rows[] = [
                    'kind' => $kind,
                    'id' => (int) $model->id,
                    'name' => (string) $model->name,
                    'deletedAt' => $model->deleted_at,
                    'deletedByUserId' => $model->deleted_by_user_id !== null ? (int) $model->deleted_by_user_id : null,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => $b['deletedAt'] <=> $a['deletedAt']);

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function expiredRootIds(ApplicationEntityKind $kind, CarbonInterface $cutoff): array
    {
        return $kind->modelClass()::onlyTrashed()
            ->whereNull('cascade_from_type')
            ->where('deleted_at', '<', $cutoff)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasBlockingDescendant(ApplicationEntityKind $kind, int $id): bool
    {
        foreach ($this->descendantRows($kind, $id) as $row) {
            $stampedOnThisRoot = $row->cascade_from_type === $kind->value
                && (int) $row->cascade_from_id === $id;

            if (! $stampedOnThisRoot) {
                return true;
            }
        }

        return false;
    }

    public function forceDeleteRoot(ApplicationEntityKind $kind, int $id): void
    {
        match ($kind) {
            ApplicationEntityKind::Tournament => $this->forceDeleteTournament($id),
            ApplicationEntityKind::LeagueSeason => $this->forceDeleteLeagueSeason($id),
            ApplicationEntityKind::League => $this->forceDeleteLeague($id),
            ApplicationEntityKind::Season => $this->forceDeleteSeason($id),
            ApplicationEntityKind::Organization => $this->forceDeleteOrganization($id),
        };
    }

    /**
     * @return list<array{0: ApplicationEntityKind, 1: list<int>}>
     */
    private function liveUnderOrganization(int $organizationId): array
    {
        $seasonIds = Season::query()->where('organization_id', $organizationId)->pluck('id')->all();
        $leagueIds = League::query()->where('organization_id', $organizationId)->pluck('id')->all();

        return [
            [ApplicationEntityKind::Season, $seasonIds],
            [ApplicationEntityKind::Tournament, $this->idsWhereIn(Tournament::class, 'season_id', $seasonIds)],
            [ApplicationEntityKind::League, $leagueIds],
            [ApplicationEntityKind::LeagueSeason, $this->idsWhereIn(LeagueSeason::class, 'league_id', $leagueIds)],
        ];
    }

    /**
     * @param  class-string  $modelClass
     * @param  list<int>  $parentIds
     * @return list<int>
     */
    private function idsWhereIn(string $modelClass, string $column, array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        return $modelClass::query()->whereIn($column, $parentIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function clearDeletion(ApplicationEntityKind $kind, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $kind->modelClass()::withTrashed()->whereIn('id', $ids)->update([
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'cascade_from_type' => null,
            'cascade_from_id' => null,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function descendantRows(ApplicationEntityKind $kind, int $id): Collection
    {
        return match ($kind) {
            ApplicationEntityKind::Organization => $this->rowsUnderOrganization($id),
            ApplicationEntityKind::Season => $this->trashedRows(ApplicationEntityKind::Tournament, 'season_id', [$id]),
            ApplicationEntityKind::League => $this->trashedRows(ApplicationEntityKind::LeagueSeason, 'league_id', [$id]),
            ApplicationEntityKind::Tournament, ApplicationEntityKind::LeagueSeason => collect(),
        };
    }

    private function rowsUnderOrganization(int $organizationId): Collection
    {
        $seasons = $this->trashedRows(ApplicationEntityKind::Season, 'organization_id', [$organizationId]);
        $leagues = $this->trashedRows(ApplicationEntityKind::League, 'organization_id', [$organizationId]);

        return $seasons
            ->concat($this->trashedRows(ApplicationEntityKind::Tournament, 'season_id', $seasons->pluck('id')->all()))
            ->concat($leagues)
            ->concat($this->trashedRows(ApplicationEntityKind::LeagueSeason, 'league_id', $leagues->pluck('id')->all()));
    }

    /**
     * @param  list<int>  $parentIds
     * @return Collection<int, object>
     */
    private function trashedRows(ApplicationEntityKind $kind, string $column, array $parentIds): Collection
    {
        if ($parentIds === []) {
            return collect();
        }

        return $kind->modelClass()::withTrashed()
            ->whereIn($column, $parentIds)
            ->get(['id', 'cascade_from_type', 'cascade_from_id']);
    }

    private function forceDeleteOrganization(int $id): void
    {
        $this->idsStamped(ApplicationEntityKind::Tournament, ApplicationEntityKind::Organization, $id)
            ->each(fn (int $tournamentId) => $this->forceDeleteTournament($tournamentId));

        $this->idsStamped(ApplicationEntityKind::Season, ApplicationEntityKind::Organization, $id)
            ->each(fn (int $seasonId) => Season::withTrashed()->whereKey($seasonId)->first()?->forceDelete());

        $this->idsStamped(ApplicationEntityKind::LeagueSeason, ApplicationEntityKind::Organization, $id)
            ->each(fn (int $leagueSeasonId) => $this->forceDeleteLeagueSeason($leagueSeasonId));

        $this->idsStamped(ApplicationEntityKind::League, ApplicationEntityKind::Organization, $id)
            ->each(fn (int $leagueId) => League::withTrashed()->whereKey($leagueId)->first()?->forceDelete());

        Organization::withTrashed()->whereKey($id)->first()?->forceDelete();
    }

    private function forceDeleteSeason(int $id): void
    {
        $this->idsStamped(ApplicationEntityKind::Tournament, ApplicationEntityKind::Season, $id)
            ->each(fn (int $tournamentId) => $this->forceDeleteTournament($tournamentId));

        Season::withTrashed()->whereKey($id)->first()?->forceDelete();
    }

    private function forceDeleteLeague(int $id): void
    {
        $this->idsStamped(ApplicationEntityKind::LeagueSeason, ApplicationEntityKind::League, $id)
            ->each(fn (int $leagueSeasonId) => $this->forceDeleteLeagueSeason($leagueSeasonId));

        League::withTrashed()->whereKey($id)->first()?->forceDelete();
    }

    private function forceDeleteTournament(int $id): void
    {
        $gameIds = DB::table('games')->where('tournament_id', $id)->pluck('id');
        $playoffIds = DB::table('playoff_games')->where('tournament_id', $id)->pluck('id');
        $this->deleteLegs($gameIds, $playoffIds, collect());
        DB::table('games')->where('tournament_id', $id)->delete();
        DB::table('achievements')->where('tournament_id', $id)->delete();
        DB::table('tournament_results')->where('tournament_id', $id)->delete();
        Tournament::withTrashed()->whereKey($id)->first()?->forceDelete();
    }

    private function forceDeleteLeagueSeason(int $id): void
    {
        $leagueGameIds = DB::table('league_games')->where('league_season_id', $id)->pluck('id');
        $this->deleteLegs(collect(), collect(), $leagueGameIds);
        LeagueSeason::withTrashed()->whereKey($id)->first()?->forceDelete();
    }

    /**
     * @return Collection<int, int>
     */
    private function idsStamped(ApplicationEntityKind $kind, ApplicationEntityKind $rootKind, int $rootId): Collection
    {
        return $kind->modelClass()::withTrashed()
            ->where('cascade_from_type', $rootKind->value)
            ->where('cascade_from_id', $rootId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    private function deleteLegs(Collection $gameIds, Collection $playoffIds, Collection $leagueGameIds): void
    {
        if ($gameIds->isEmpty() && $playoffIds->isEmpty() && $leagueGameIds->isEmpty()) {
            return;
        }

        DB::table('game_legs')->where(function ($query) use ($gameIds, $playoffIds, $leagueGameIds) {
            if ($gameIds->isNotEmpty()) {
                $query->orWhereIn('game_id', $gameIds);
            }
            if ($playoffIds->isNotEmpty()) {
                $query->orWhereIn('playoff_game_id', $playoffIds);
            }
            if ($leagueGameIds->isNotEmpty()) {
                $query->orWhereIn('league_game_id', $leagueGameIds);
            }
        })->delete();
    }
}
