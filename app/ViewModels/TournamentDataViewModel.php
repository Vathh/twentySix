<?php

namespace App\ViewModels;

use App\Domain\AchievementDomain;
use App\Domain\Game\GroupGameDomain;
use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\PlayerDomain;
use App\Domain\SeasonDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\AchievementType;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentDataViewModel
{

    public function __construct(
        public Tournament $tournament
    )
    {
    }

    /**
     * @return array<GroupStandingDomain>
     */
    public function groupStandings(): array
    {
        $result = [];

        $standingsDomains = $this->tournament
                                ->groupStandings
                                ->map(fn($standing) => GroupStandingDomain::fromEloquent($standing, ['player']));

        foreach ($standingsDomains as $standing)
        {
            $result[$standing->groupNumber][$standing->player->id] = $standing;
        }

        return $result;
    }

    /**
     * @return array<GroupGameDomain>
     */
    public function games(): array
    {
        $result = [];

        $gameDomains = $this->tournament
                    ->games->map(fn($game) => GroupGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player1->id][$game->player2->id] = $game;
        }

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player2->id][$game->player1->id] = $game;
        }

        return $result;
    }

    public function playoffGames(): array
    {
        $result = [];

        $playoffGameDomains = $this->tournament
                                    ->playoffGames
                                    ->map(fn($game) => PlayoffGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($playoffGameDomains as $game) {
            $result[$game->round][] = $game;
        }

        return $result;
    }

    /**
     * @return array<PlayerDomain>
     */
    public function players(): array
    {
        $result = [];

        foreach ($this->tournament->groupStandings as $standing)
        {
            $result[$standing->group_number][] = PlayerDomain::fromEloquent($standing->player);
        }

        return $result;
    }

    public function groupNumbers(): Collection
    {
        return $this->tournament
                    ->groupStandings
                    ->map(fn($standing) => $standing->group_number)
                    ->unique()
                    ->sort()
                    ->collect();
    }

    /**
     * Podświetlenie awansu do playoff w tabelach grup (gdy grupa domknięta).
     *
     * @return array<int, array{complete: bool, advanceCount: int, advancingPlayerIds: list<int>}>
     */
    public function groupPlayoffHighlights(): array
    {
        $advancesList = $this->tournament->group_advances;
        if (! is_array($advancesList) || $advancesList === []) {
            return [];
        }

        $gamesByGroup = $this->games();
        $standingsByGroup = $this->groupStandings();
        $result = [];

        foreach ($this->groupNumbers() as $groupNumber) {
            $advanceCount = (int) ($advancesList[$groupNumber - 1] ?? $advancesList[(string) ($groupNumber - 1)] ?? 0);
            $complete = $this->isGroupFinished($gamesByGroup[$groupNumber] ?? []);
            $advancingPlayerIds = [];

            if ($complete && $advanceCount > 0) {
                foreach ($standingsByGroup[$groupNumber] ?? [] as $playerId => $standing) {
                    if ($standing->place > 0 && $standing->place <= $advanceCount) {
                        $advancingPlayerIds[] = (int) $playerId;
                    }
                }
            }

            $result[(int) $groupNumber] = [
                'complete' => $complete,
                'advanceCount' => $advanceCount,
                'advancingPlayerIds' => $advancingPlayerIds,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<int, GroupGameDomain>>  $pairMatrix
     */
    private function isGroupFinished(array $pairMatrix): bool
    {
        $seen = [];
        $hasGames = false;

        foreach ($pairMatrix as $opponents) {
            foreach ($opponents as $game) {
                if (isset($seen[$game->id])) {
                    continue;
                }
                $seen[$game->id] = true;
                $hasGames = true;
                if (! $game->isFinished()) {
                    return false;
                }
            }
        }

        return $hasGames;
    }

    /**
     * @return \App\Domain\Tournament\TournamentDomain
     */
    public function tournament(): TournamentDomain
    {
        return TournamentDomain::fromEloquent($this->tournament, ['pointScheme', 'season']);
    }

    /**
     * @return SeasonDomain|null
     */
    public function season(): ?SeasonDomain
    {
        if ($this->tournament->season === null) {
            return null;
        }

        return SeasonDomain::fromEloquent($this->tournament->season, ['organization', 'admins']);
    }

    /**
     * @return Collection<int, array{player: PlayerDomain, max: int, one_seventy: int, qf: list<AchievementDomain|\stdClass>, hf: list<AchievementDomain>}>
     */
    public function achievements(): Collection
    {
        $achievementDomains = $this->tournament
                                    ->achievements
                                    ->map(fn($achievement) => AchievementDomain::fromEloquent($achievement, ['player']));

        $result = [];

        foreach ($achievementDomains as $achievement) {
            if ($achievement->player === null) {
                continue;
            }

            $playerId = $achievement->player->id;

            if (! isset($result[$playerId]['player'])) {
                $result[$playerId]['player'] = $achievement->player;
                $result[$playerId]['max'] = 0;
                $result[$playerId]['one_seventy'] = 0;
                $result[$playerId]['qf'] = [];
                $result[$playerId]['hf'] = [];
            }

            switch ($achievement->type) {
                case AchievementType::ONE_SEVENTY:
                case AchievementType::MAX:
                    $result[$playerId][$achievement->type->value]++;
                    break;
                case AchievementType::HF:
                case AchievementType::QF:
                    $result[$playerId][$achievement->type->value][] = $achievement;
                    break;
            }
        }

        // QF z scoringu (darts_thrown < 20 na wygranym legu) — źródło prawdy po tablecie;
        // client POST często pomijał QF w ścieżce online.
        $qfFromScoring = $this->quickFinishesFromScoring();
        if ($qfFromScoring !== []) {
            $playersById = Player::query()
                ->whereIn('id', array_keys($qfFromScoring))
                ->get()
                ->keyBy('id');

            foreach ($qfFromScoring as $playerId => $dartCounts) {
                if (! isset($result[$playerId]['player'])) {
                    $player = $playersById->get($playerId);
                    if ($player === null) {
                        continue;
                    }
                    $result[$playerId]['player'] = PlayerDomain::fromEloquent($player);
                    $result[$playerId]['max'] = 0;
                    $result[$playerId]['one_seventy'] = 0;
                    $result[$playerId]['hf'] = [];
                }

                $result[$playerId]['qf'] = array_map(
                    static fn (int $darts) => (object) ['value' => $darts],
                    $dartCounts,
                );
            }
        }

        return collect($result);
    }

    /**
     * @return array<int, list<int>> player_id => lista darts_thrown (QF)
     */
    private function quickFinishesFromScoring(): array
    {
        $tournamentId = (int) $this->tournament->id;

        $rows = DB::table('game_leg_player_stats as glps')
            ->join('game_legs as gl', 'gl.id', '=', 'glps.game_leg_id')
            ->leftJoin('games as g', 'g.id', '=', 'gl.game_id')
            ->leftJoin('playoff_games as pg', 'pg.id', '=', 'gl.playoff_game_id')
            ->where(function ($q) use ($tournamentId) {
                $q->where('g.tournament_id', $tournamentId)
                    ->orWhere('pg.tournament_id', $tournamentId);
            })
            ->whereColumn('gl.winner_id', 'glps.player_id')
            ->whereNotNull('gl.finished_at')
            ->whereNotNull('glps.darts_thrown')
            ->where('glps.darts_thrown', '<', 20)
            ->orderBy('glps.darts_thrown')
            ->get(['glps.player_id', 'glps.darts_thrown']);

        $byPlayer = [];
        foreach ($rows as $row) {
            $byPlayer[(int) $row->player_id][] = (int) $row->darts_thrown;
        }

        return $byPlayer;
    }

    public function results(): Collection
    {
        $resultDomains = $this->tournament
            ->results
            ->map(fn ($result) => TournamentResultDomain::fromEloquent($result, ['player']));

        return $resultDomains
            ->sortBy(fn ($result) => $result->place ?? PHP_INT_MAX)
            ->values()
            ->map(fn ($result) => [
                'player' => $result->player,
                'place' => $result->place,
                'points' => $result->points,
                'stage' => $result->eliminationStage,
            ]);
    }
}

