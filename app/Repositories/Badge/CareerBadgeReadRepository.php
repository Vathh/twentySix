<?php

namespace App\Repositories\Badge;

use App\Enums\GameStatus;
use App\Enums\LeagueGameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\League\LeagueGame;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CareerBadgeReadRepository
{
    /**
     * Wygrane i przegrane w kolejności zakończenia: grupa, playoff i liga.
     * Quick game nie wchodzi. Mecz z bye, void albo bez zwycięzcy też nie.
     *
     * @return list<bool>
     */
    public function winsInOrder(int $playerId): array
    {
        return array_map(fn (array $row) => $row['win'], $this->streakRows($playerId));
    }

    /**
     * @return list<array{at: mixed, kind: string, id: int, win: bool}>
     */
    public function streakRows(int $playerId): array
    {
        $rows = [];

        foreach ($this->streakSources() as [$model, $status, $kind]) {
            $games = $this->streakQuery($model, $status)
                ->where(function (Builder $query) use ($playerId) {
                    $query->where('player1_id', $playerId)
                        ->orWhere('player2_id', $playerId);
                })
                ->get(['id', 'winner_id', 'finished_at']);

            foreach ($games as $game) {
                $rows[] = [
                    'at' => $game->finished_at,
                    'kind' => $kind,
                    'id' => (int) $game->id,
                    'win' => (int) $game->winner_id === $playerId,
                ];
            }
        }

        usort($rows, function (array $left, array $right): int {
            $byTime = $left['at'] <=> $right['at'];
            if ($byTime !== 0) {
                return $byTime;
            }

            $byKind = $left['kind'] <=> $right['kind'];
            if ($byKind !== 0) {
                return $byKind;
            }

            return $left['id'] <=> $right['id'];
        });

        return $rows;
    }

    /**
     * @return list<array{kind: string, id: int}>
     */
    public function finishedCompetitiveIds(): array
    {
        $rows = [];

        foreach ($this->streakSources() as [$model, $status, $kind]) {
            $ids = $model::query()->where('status', $status)->orderBy('id')->pluck('id');
            foreach ($ids as $id) {
                $rows[] = ['kind' => $kind, 'id' => (int) $id];
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function streakPlayerIds(): array
    {
        $ids = [];

        foreach ($this->streakSources() as [$model, $status]) {
            $games = $this->streakQuery($model, $status)->get(['player1_id', 'player2_id']);
            foreach ($games as $game) {
                $ids[] = (int) $game->player1_id;
                $ids[] = (int) $game->player2_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function finishedTournamentIds(): array
    {
        return Tournament::query()
            ->where('status', TournamentStatus::FINISHED)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function tournamentIsFinished(int $tournamentId): bool
    {
        return Tournament::query()
            ->whereKey($tournamentId)
            ->where('status', TournamentStatus::FINISHED)
            ->exists();
    }

    /**
     * Zawodnicy, którzy rozegrali w turnieju co najmniej jeden mecz bez bye.
     *
     * @return list<int>
     */
    public function playedPlayerIds(int $tournamentId): array
    {
        $ids = [];

        foreach ([Game::class, PlayoffGame::class] as $model) {
            $games = $model::query()
                ->where('tournament_id', $tournamentId)
                ->where('status', GameStatus::FINISHED)
                ->whereNotNull('winner_id')
                ->whereHas('player1', fn (Builder $query) => $query->where('is_bye', false))
                ->whereHas('player2', fn (Builder $query) => $query->where('is_bye', false))
                ->get(['player1_id', 'player2_id']);

            foreach ($games as $game) {
                $ids[] = (int) $game->player1_id;
                $ids[] = (int) $game->player2_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Wszyscy wpisani w mecze turnieju, także po resecie wyniku.
     *
     * @return list<int>
     */
    public function participantIds(int $tournamentId): array
    {
        $ids = [];

        foreach ([Game::class, PlayoffGame::class] as $model) {
            $games = $model::query()
                ->where('tournament_id', $tournamentId)
                ->get(['player1_id', 'player2_id']);

            foreach ($games as $game) {
                if ($game->player1_id !== null) {
                    $ids[] = (int) $game->player1_id;
                }
                if ($game->player2_id !== null) {
                    $ids[] = (int) $game->player2_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int> player_id => place
     */
    public function places(int $tournamentId): array
    {
        $places = [];

        $rows = TournamentResult::query()
            ->where('tournament_id', $tournamentId)
            ->whereNotNull('place')
            ->get(['player_id', 'place']);

        foreach ($rows as $row) {
            $places[(int) $row->player_id] = (int) $row->place;
        }

        return $places;
    }

    /**
     * @param  list<int>  $tournamentIds
     * @return array<int, string>
     */
    public function tournamentNames(array $tournamentIds): array
    {
        if ($tournamentIds === []) {
            return [];
        }

        $names = [];
        $rows = Tournament::query()
            ->whereIn('id', $tournamentIds)
            ->get(['id', 'name']);

        foreach ($rows as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /**
     * @param  list<int>  $gameIds
     * @return list<int>
     */
    public function playerIdsOnLeagueGames(array $gameIds): array
    {
        if ($gameIds === []) {
            return [];
        }

        $ids = [];
        $games = LeagueGame::query()
            ->whereIn('id', $gameIds)
            ->get(['player1_id', 'player2_id']);

        foreach ($games as $game) {
            $ids[] = (int) $game->player1_id;
            $ids[] = (int) $game->player2_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function playerIdsInLeagueSeason(int $seasonId): array
    {
        $ids = [];
        $games = LeagueGame::query()
            ->where('league_season_id', $seasonId)
            ->get(['player1_id', 'player2_id']);

        foreach ($games as $game) {
            $ids[] = (int) $game->player1_id;
            $ids[] = (int) $game->player2_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{0: class-string<Model>, 1: GameStatus|LeagueGameStatus}>
     */
    private function streakSources(): array
    {
        return [
            [Game::class, GameStatus::FINISHED, 'group'],
            [PlayoffGame::class, GameStatus::FINISHED, 'playoff'],
            [LeagueGame::class, LeagueGameStatus::FINISHED, 'league'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function streakQuery(string $model, GameStatus|LeagueGameStatus $status): Builder
    {
        return $model::query()
            ->where('status', $status)
            ->whereNotNull('winner_id')
            ->whereNotNull('finished_at')
            ->whereHas('player1', fn (Builder $query) => $query->where('is_bye', false))
            ->whereHas('player2', fn (Builder $query) => $query->where('is_bye', false));
    }
}
