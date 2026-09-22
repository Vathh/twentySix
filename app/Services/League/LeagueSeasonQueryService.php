<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\Stats\ThreeDartAverageSet;
use App\Domain\League\LeagueMatchdayCalendar;
use App\Domain\League\LeagueStandingRow;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;
use App\Models\League\LeagueSeasonMatchday;
use App\Repositories\League\LeagueSeasonRepository;
use App\Services\Stats\CompetitionThreeDartAverageService;

class LeagueSeasonQueryService
{
    public function __construct(
        private LeagueSeasonRepository $leagueSeasonRepository,
        private LeagueSeasonStandingsBuilder $standings,
        private LeagueSeasonLifecycleService $lifecycle,
        private CompetitionThreeDartAverageService $threeDartAverages,
    ) {}

    public function getForPolicy(int $seasonId): LeagueSeason
    {
        return $this->leagueSeasonRepository->findWithGraph($seasonId);
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(int $seasonId): array
    {
        $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
        $withdrawnIds = $season->participants->whereNotNull('withdrawn_at')->pluck('player_id')->all();
        $divisions = $this->standings->divisionBlocks($season);

        $playoffGames = $season->games->where('purpose', LeagueGamePurpose::PROMOTION_PLAYOFF)->values();
        $tiebreakGames = $season->games->where('purpose', LeagueGamePurpose::TIEBREAKER)->values();

        $startReadiness = $season->status === LeagueSeasonStatus::DRAFT
            ? $this->lifecycle->startReadinessForLeague($season->league_id)
            : null;

        return [
            'season' => $season,
            'league' => $season->league,
            'organization' => $season->league->organization,
            'divisions' => $divisions,
            'playoffGames' => $playoffGames,
            'tiebreakGames' => $tiebreakGames,
            'withdrawnIds' => $withdrawnIds,
            'canAdvance' => $season->status->isOpen() && $this->standings->regularPhaseComplete($season),
            'canStartSeason' => $startReadiness?->canStart ?? false,
            'startBlockedReason' => $startReadiness?->canStart ? null : $startReadiness?->reason,
            'threeDartAverages' => $this->threeDartAverages->forLeagueMatches(
                $season->games->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ),
        ];
    }

    /**
     * @return list<array{division: LeagueSeasonDivision, standings: list<LeagueStandingRow>, games: \Illuminate\Support\Collection<int, LeagueGame>, players: \Illuminate\Support\Collection}>
     */
    public function divisionBlocks(LeagueSeason $season): array
    {
        return $this->standings->divisionBlocks($season);
    }

    /**
     * @return array{division: LeagueSeasonDivision, standings: list<LeagueStandingRow>, games: \Illuminate\Support\Collection<int, LeagueGame>, players: \Illuminate\Support\Collection}|null
     */
    public function archiveBlockForDivision(LeagueSeason $season, int $leagueDivisionId): ?array
    {
        foreach ($this->standings->divisionBlocks($season) as $block) {
            if ((int) $block['division']->league_division_id === $leagueDivisionId) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function showForApi(int $seasonId): array
    {
        $data = $this->showData($seasonId);
        /** @var LeagueSeason $season */
        $season = $data['season'];
        $league = $data['league'];
        $organization = $data['organization'];
        $status = $season->status;
        $participants = $season->participants;

        $divisions = [];
        $averages = $data['threeDartAverages'];
        foreach ($data['divisions'] as $block) {
            $division = $block['division'];
            /** @var \Illuminate\Support\Collection<int, LeagueGame> $regularGames */
            $regularGames = $block['games'];
            $standings = [];
            foreach ($block['standings'] as $row) {
                /** @var LeagueStandingRow $row */
                $participant = $participants->firstWhere('player_id', $row->playerId);
                $standings[] = [
                    'place' => $row->place,
                    'playerId' => $row->playerId,
                    'playerName' => $participant?->player?->name ?? ('#'.$row->playerId),
                    'userId' => $participant?->player?->user_id,
                    'played' => $row->played,
                    'average' => $averages->leaguePlayerAverage((int) $row->playerId),
                    'wins' => $row->wins,
                    'draws' => $row->draws,
                    'losses' => $row->losses,
                    'points' => $row->points,
                    'unitDiff' => $row->unitDiff,
                    'needsTiebreak' => $row->needsTiebreak,
                ];
            }

            $rounds = [];
            if ($season->matchdays->isNotEmpty()) {
                foreach ($season->matchdays as $matchday) {
                    $roundGames = $regularGames->where('league_season_matchday_id', $matchday->id)->values();
                    if ($roundGames->isEmpty()) {
                        continue;
                    }
                    $mapped = $this->mapMatchdayForApi($matchday);
                    $mapped['games'] = $roundGames->map(fn (LeagueGame $game) => $this->mapGameForApi($game, $averages))->all();
                    $rounds[] = $mapped;
                }
            }

            $divisions[] = [
                'id' => $division->id,
                'name' => $division->name,
                'position' => (int) $division->position,
                'standings' => $standings,
                'rounds' => $rounds,
                'games' => $season->matchdays->isEmpty()
                    ? $regularGames->map(fn (LeagueGame $game) => $this->mapGameForApi($game, $averages))->values()->all()
                    : [],
            ];
        }

        return [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'status' => $status->value,
                'statusLabel' => $status->label(),
                'statusVariant' => $status->isOpen()
                    ? 'live'
                    : ($status === LeagueSeasonStatus::FINISHED ? 'finished' : 'planned'),
                'calendarMode' => $season->calendar_mode->value,
                'calendarModeLabel' => $season->calendar_mode->label(),
                'matchdayPlanning' => $season->matchday_planning?->value,
                'matchdayLengthDays' => $season->matchday_length_days,
                'matchdayLengthLabel' => $season->matchday_length_days
                    ? LeagueMatchdayCalendar::lengthLabel((int) $season->matchday_length_days)
                    : null,
                'roundsEach' => (int) $season->rounds_each,
                'allowsDraws' => (bool) $season->allows_draws,
                'winMode' => $season->win_mode->value,
                'winLength' => (int) $season->win_length,
                'formatLabel' => $season->allows_draws
                    ? 'Best of '.$season->win_length.' (z remisami)'
                    : 'First to '.$season->win_length,
                'startDate' => $season->start_date?->format('Y-m-d'),
                'endDate' => $season->end_date?->format('Y-m-d'),
            ],
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
            ],
            'organization' => $organization
                ? ['id' => $organization->id, 'name' => $organization->name]
                : null,
            'divisions' => $divisions,
            'tiebreakGames' => collect($data['tiebreakGames'])->map(fn (LeagueGame $game) => $this->mapGameForApi($game, $averages))->values()->all(),
            'playoffGames' => collect($data['playoffGames'])->map(fn (LeagueGame $game) => $this->mapGameForApi($game, $averages))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function gameShowData(int $gameId): array
    {
        $game = $this->leagueSeasonRepository->findGame($gameId);
        $format = MatchFormat::fromRecord($game);

        return [
            'game' => $game,
            'season' => $game->season,
            'league' => $game->season->league,
            'organization' => $game->season->league->organization,
            'format' => $format,
            'canManage' => $game->season->status->isOpen()
                && $game->status !== LeagueGameStatus::VOIDED
                && ! in_array($game->status, [LeagueGameStatus::LOBBY, LeagueGameStatus::IN_PROGRESS], true),
            'canCancelGame' => $game->season->status->isOpen()
                && in_array($game->status, [LeagueGameStatus::LOBBY, LeagueGameStatus::IN_PROGRESS], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMatchdayForApi(LeagueSeasonMatchday $matchday): array
    {
        return [
            'id' => $matchday->id,
            'roundNumber' => (int) $matchday->round_number,
            'windowLabel' => $matchday->windowLabel(),
            'isCurrent' => $matchday->isCurrent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGameForApi(LeagueGame $game, ThreeDartAverageSet $averages): array
    {
        return [
            'id' => $game->id,
            'player1' => [
                'id' => $game->player1_id,
                'name' => $game->player1?->name ?? ('#'.$game->player1_id),
                'userId' => $game->player1?->user_id,
            ],
            'player2' => [
                'id' => $game->player2_id,
                'name' => $game->player2?->name ?? ('#'.$game->player2_id),
                'userId' => $game->player2?->user_id,
            ],
            'player1Score' => $game->player1_score,
            'player2Score' => $game->player2_score,
            'player1Average' => $averages->leagueMatchAverage((int) $game->id, (int) $game->player1_id),
            'player2Average' => $averages->leagueMatchAverage((int) $game->id, (int) $game->player2_id),
            'status' => $game->status->value,
            'isThirdPlace' => (bool) $game->is_third_place,
            'matchdayId' => $game->league_season_matchday_id,
        ];
    }
}
