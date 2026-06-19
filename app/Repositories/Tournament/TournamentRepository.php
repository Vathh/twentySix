<?php

namespace App\Repositories\Tournament;

use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\TournamentStatus;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Collection;
use Throwable;

class TournamentRepository
{
    /**
     * @return Collection<int, TournamentDomain>
     */
    public function getAll(): Collection
    {
        return Tournament::query()
            ->with(['season.league'])
            ->get()
            ->map(fn (Tournament $tournament) => TournamentDomain::fromEloquent($tournament, ['season']));
    }

    public function create(
        ?int    $seasonId,
        string  $name,
        ?string $date
    ): int {
        $tournament = Tournament::create([
            'season_id' => $seasonId,
            'name' => $name,
            'date' => $date,
        ]);

        return (int) $tournament->id;
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

        return $tournament->status === TournamentStatus::CREATED;
    }


    /**
     * @param int $tournamentId
     * @return TournamentDomain|null
     */
    public function findWithSeasonAndPointSchemeRules(int $tournamentId): ?TournamentDomain
    {
        $tournament = Tournament::with(['season.league', 'pointScheme.rules'])->findOrFail($tournamentId);

        return TournamentDomain::fromEloquent($tournament, ['season', 'pointScheme', 'pointScheme.rules']);
    }

    public function findWithSeasonAndPointScheme(int $tournamentId): ?TournamentDomain
    {
        $tournament = Tournament::with(['season.league', 'pointScheme'])->findOrFail($tournamentId);

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
        int $groupsCount,
        int $advancePerGroup,
        int $tabletsCount,
    ): void {
        Tournament::where('id', $tournamentId)->update([
            'groups_count' => $groupsCount,
            'advance_per_group' => $advancePerGroup,
            'tablets_count' => $tabletsCount,
        ]);
    }

    /**
     * Awans z grupy zapisany przy starcie turnieju. Dla starych rekordów bez configu: domyślnie 2.
     */
    public function getAdvancePerGroup(int $tournamentId): int
    {
        $advance = Tournament::where('id', $tournamentId)->value('advance_per_group');

        return $advance !== null ? (int) $advance : 2;
    }

    public function getBracketSize(int $tournamentId): int
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if ($tournament->groups_count !== null && $tournament->advance_per_group !== null) {
            return $tournament->groups_count * $tournament->advance_per_group;
        }

        return 16;
    }

    /**
     * Zwraca league_id dla turnieju (przez sezon). Null jeśli turniej nie ma sezonu.
     */
    public function getLeagueIdForTournament(int $tournamentId): ?int
    {
        $tournament = Tournament::with('season')->find($tournamentId);
        return $tournament?->season?->league_id;
    }
}












