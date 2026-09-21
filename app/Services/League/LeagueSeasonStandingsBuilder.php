<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\League\LeagueStandingCalculator;
use App\Domain\League\LeagueStandingRow;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\MatchWinMode;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;

class LeagueSeasonStandingsBuilder
{
    /**
     * @return list<array{division: LeagueSeasonDivision, standings: list<LeagueStandingRow>, games: \Illuminate\Support\Collection<int, LeagueGame>, players: \Illuminate\Support\Collection}>
     */
    public function divisionBlocks(LeagueSeason $season): array
    {
        $divisions = [];

        foreach ($season->divisions as $division) {
            $activeIds = $this->activePlayerIds($division);
            $regularGames = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $standings = LeagueStandingCalculator::calculate(
                $activeIds,
                $regularGames->map(fn (LeagueGame $game) => $this->toStandingGame($game))->all(),
                (bool) $season->allows_draws,
            );

            $divisions[] = [
                'division' => $division,
                'standings' => $standings,
                'games' => $regularGames->values(),
                'players' => $division->participants->keyBy('player_id'),
            ];
        }

        return $divisions;
    }

    public function regularPhaseComplete(LeagueSeason $season): bool
    {
        return $season->games
            ->where('purpose', LeagueGamePurpose::REGULAR)
            ->filter(fn (LeagueGame $game) => ! in_array(
                $game->status,
                [LeagueGameStatus::FINISHED, LeagueGameStatus::VOIDED],
                true,
            ))
            ->isEmpty();
    }

    public function seasonMatchFormat(
        LeagueSeason $season,
        int $startingScore,
        bool $mustHaveWinner = false,
        ?int $dartLimit = null,
        ?int $lossThreshold = null,
    ): MatchFormat {
        if ($mustHaveWinner || ! $season->allows_draws) {
            $length = $season->allows_draws
                ? intdiv((int) $season->win_length, 2) + 1
                : (int) $season->win_length;

            return MatchFormat::forLeagueRules(
                $startingScore,
                MatchWinMode::FIRST_TO,
                $length,
                $dartLimit,
                $lossThreshold,
            );
        }

        return MatchFormat::forLeagueRules(
            $startingScore,
            $season->win_mode,
            (int) $season->win_length,
            $dartLimit,
            $lossThreshold,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function formatColumns(MatchFormat $format): array
    {
        return [
            ...$format->toDatabaseColumns(),
            'win_mode' => $format->winMode,
            'win_length' => $format->winLength,
        ];
    }

    /**
     * @return array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}
     */
    public function toStandingGame(LeagueGame $game): array
    {
        return [
            'player1Id' => $game->player1_id,
            'player2Id' => $game->player2_id,
            'player1Score' => (int) ($game->player1_score ?? 0),
            'player2Score' => (int) ($game->player2_score ?? 0),
            'winnerId' => $game->winner_id,
            'status' => $game->status->value,
            'walkoverType' => $game->walkover_type->value,
        ];
    }

    /**
     * @return list<int>
     */
    public function activePlayerIds(LeagueSeasonDivision $division): array
    {
        return $division->participants
            ->filter(fn ($participant) => $participant->withdrawn_at === null)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
