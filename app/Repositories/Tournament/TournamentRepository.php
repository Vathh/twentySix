<?php

namespace App\Repositories\Tournament;

use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\TournamentStatus;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Collection;
use Throwable;

class TournamentRepository
{
    public const INDEX_PER_PAGE = 9;

    /**
     * @return Collection<int, TournamentDomain>
     */
    public function getAll(): Collection
    {
        return Tournament::query()
            ->with(['season.organization'])
            ->get()
            ->map(fn (Tournament $tournament) => TournamentDomain::fromEloquent($tournament, ['season']));
    }

    /**
     * Strona listy turniejów (najpierw najnowsze daty rozgrywek).
     *
     * @return array{items: Collection<int, TournamentDomain>, has_more: bool}
     */
    public function getPage(int $page): array
    {
        $page = max(1, $page);
        $paginator = Tournament::query()
            ->with(['season.organization'])
            ->orderByRaw('COALESCE(date, ?) DESC', ['1970-01-01'])
            ->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Tournament $tournament) => TournamentDomain::fromEloquent($tournament, ['season']))
                ->values(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    public function create(
        ?int    $seasonId,
        string  $name,
        ?string $date,
        ?int    $createdByUserId = null,
    ): int {
        $tournament = Tournament::create([
            'season_id' => $seasonId,
            'name' => $name,
            'date' => $date,
        ]);

        if ($createdByUserId !== null) {
            $tournament->admins()->attach($createdByUserId);
        }

        if ($seasonId !== null) {
            $seasonAdminIds = Season::query()
                ->with('admins')
                ->findOrFail($seasonId)
                ->admins
                ->pluck('id');
            if ($seasonAdminIds->isNotEmpty()) {
                $tournament->admins()->syncWithoutDetaching($seasonAdminIds->all());
            }
        }

        return (int) $tournament->id;
    }

    /**
     * Surowy model Eloquent (np. do autoryzacji lub przekazania do serwisów operujących na Tournament).
     */
    public function findModel(int $tournamentId, array $relations = []): Tournament
    {
        return Tournament::with($relations)->findOrFail($tournamentId);
    }

    public function addAdmin(int $tournamentId, int $userId): void
    {
        Tournament::findOrFail($tournamentId)->admins()->syncWithoutDetaching([$userId]);
    }

    public function removeAdmin(int $tournamentId, int $userId): void
    {
        $tournament = Tournament::withCount('admins')->findOrFail($tournamentId);
        if ((int) $tournament->admins_count <= 1) {
            throw new \DomainException('Turniej musi mieć co najmniej jednego administratora.');
        }
        $tournament->admins()->detach($userId);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function getAdmins(int $tournamentId): Collection
    {
        return Tournament::findOrFail($tournamentId)
            ->admins()
            ->with('player')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->player?->name ?? $user->email,
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * @throws Throwable
     */
    public function changeStatus(int $tournamentId, TournamentStatus $status): void
    {
        Tournament::where('id', $tournamentId)->update(['status' => $status]);
    }

    public function checkIfTournamentCanBeStarted(int $tournamentId): bool
    {
        $tournament = Tournament::findOrFail($tournamentId);

        return TournamentDomain::fromEloquent($tournament)->canTransitionTo(TournamentStatus::GROUP);
    }


    /**
     * @param int $tournamentId
     * @return TournamentDomain|null
     */
    public function findWithSeasonAndPointSchemeRules(int $tournamentId): ?TournamentDomain
    {
        $tournament = Tournament::with(['season.organization', 'pointScheme.rules'])->findOrFail($tournamentId);

        return TournamentDomain::fromEloquent($tournament, ['season', 'pointScheme', 'pointScheme.rules']);
    }

    public function findWithSeasonAndPointScheme(int $tournamentId): ?TournamentDomain
    {
        $tournament = Tournament::with(['season.organization', 'pointScheme'])->findOrFail($tournamentId);

        return TournamentDomain::fromEloquent($tournament, ['season', 'pointScheme']);
    }

    /**
     * @throws Throwable
     */
    public function finishTournament(int $tournamentId): void
    {
        $tournament = Tournament::with(['games', 'playoffGames'])->findOrFail($tournamentId);

        if($tournament->games->count() === 0 && $tournament->playoffGames->count() === 0) {
            $this->changeStatus($tournamentId, TournamentStatus::FINISHED);
        }
    }

    public function updatePointSchemeId(int $tournamentId, int $pointSchemeId): void
    {
        Tournament::where('id', $tournamentId)->update(['point_scheme_id' => $pointSchemeId]);
    }

    public function saveStartConfiguration(
        int $tournamentId,
        int $playoffBracketSize,
        int $tabletsCount,
        \App\Enums\TournamentFormat $format = \App\Enums\TournamentFormat::GroupsPlayoff,
        ?int $groupsCount = null,
        ?array $groupAdvances = null,
        ?\App\Enums\GrandFinalMode $grandFinalMode = null,
    ): void {
        Tournament::where('id', $tournamentId)->update([
            'format' => $format,
            'grand_final_mode' => $grandFinalMode,
            'groups_count' => $groupsCount,
            'playoff_bracket_size' => $playoffBracketSize,
            'group_advances' => $groupAdvances,
            'tablets_count' => $tabletsCount,
        ]);
    }

    public function getBracketSize(int $tournamentId): int
    {
        $bracketSize = Tournament::where('id', $tournamentId)->value('playoff_bracket_size');

        if ($bracketSize === null) {
            throw new \RuntimeException('Turniej nie ma zapisanej konfiguracji drabinki playoff.');
        }

        return (int) $bracketSize;
    }

    /**
     * @return list<int> liczba awansujących per grupa (indeks 0 = grupa 1)
     */
    public function getGroupAdvances(int $tournamentId): array
    {
        $groupAdvances = Tournament::query()
            ->findOrFail($tournamentId)
            ->group_advances;

        if (! is_array($groupAdvances) || $groupAdvances === []) {
            throw new \RuntimeException('Turniej nie ma zapisanego rozkładu awansu z grup.');
        }

        return array_values($groupAdvances);
    }

    /**
     * @return array<int, int> group_number => advances_count
     */
    public function getGroupAdvancesByGroupNumber(int $tournamentId): array
    {
        $map = [];

        foreach ($this->getGroupAdvances($tournamentId) as $index => $count) {
            $map[$index + 1] = $count;
        }

        return $map;
    }

    /**
     * Zwraca organization_id dla turnieju (przez sezon). Null jeśli turniej nie ma sezonu.
     */
    public function getOrganizationIdForTournament(int $tournamentId): ?int
    {
        $tournament = Tournament::with('season')->find($tournamentId);
        return $tournament?->season?->organization_id;
    }

    /**
     * Surowy model Eloquent, null gdy nie istnieje (bez wyjątku).
     */
    public function findModelOrNull(int $tournamentId, array $relations = []): ?Tournament
    {
        return Tournament::with($relations)->find($tournamentId);
    }

    public function joinCodeExists(string $code): bool
    {
        return Tournament::where('join_code', $code)->exists();
    }

    public function findByJoinCode(string $code): ?Tournament
    {
        return Tournament::with(['season.organization'])
            ->where('join_code', $code)
            ->first();
    }

    public function setJoinCode(Tournament $tournament, string $code): Tournament
    {
        $tournament->join_code = $code;
        $tournament->join_code_generated_at = now();
        $tournament->join_code_enabled = true;
        $tournament->save();

        return $tournament->fresh();
    }

    public function setJoinCodeEnabled(Tournament $tournament, bool $enabled): Tournament
    {
        $tournament->join_code_enabled = $enabled;
        $tournament->save();

        return $tournament->fresh();
    }
}











