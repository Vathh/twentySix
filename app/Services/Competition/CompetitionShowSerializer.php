<?php

namespace App\Services\Competition;

use App\Domain\Game\GroupGameDomain;
use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\LeagueDomain;
use App\Domain\SeasonDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStage;
use App\Models\League\League;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Queries\GetTournamentData;
use App\Services\Season\SeasonStatsService;
use App\ViewModels\TournamentDataViewModel;
use Illuminate\Support\Collection;

class CompetitionShowSerializer
{
    public function __construct(
        private SeasonStatsService $seasonStatsService,
        private GetTournamentData $getTournamentData,
    ) {
    }

    /**
     * @return array{league: array, seasons: list<array>}
     */
    public function league(League $league): array
    {
        $domain = LeagueDomain::fromEloquent($league, ['seasons']);
        $seasons = collect($domain->seasons)
            ->sortByDesc(fn (SeasonDomain $season) => $season->updatedAt?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (SeasonDomain $season) => [
                'id' => $season->id,
                'name' => $season->name,
            ])
            ->all();

        return [
            'league' => [
                'id' => $domain->id,
                'name' => $domain->name,
                'description' => $domain->description,
                'createdAt' => $domain->createdAtDate(),
                'updatedAt' => $domain->updatedAtDate(),
            ],
            'seasons' => $seasons,
        ];
    }

    /**
     * @return array{season: array, league: ?array{id: int, name: string}, tournaments: list<array>, standings: list<array>, standingsHasMore: bool}
     */
    public function season(Season $season): array
    {
        $domain = SeasonDomain::fromEloquent($season, ['league', 'tournaments']);

        $tournaments = $domain->tournaments
            ->sortByDesc(fn (TournamentDomain $t) => $t->date?->getTimestamp() ?? PHP_INT_MIN)
            ->values()
            ->map(fn (TournamentDomain $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'date' => $t->getDate(),
                'statusLabel' => $t->status->label(),
                'statusVariant' => $t->status->badgeVariant(),
            ])
            ->all();

        $standingsPage = $this->seasonStatsService->getStandingsPage($season->id, 1);

        return [
            'season' => [
                'id' => $domain->id,
                'name' => $domain->name,
                'startDate' => $domain->getStartDate(),
                'endDate' => $domain->getEndDate(),
                'updatedAt' => $domain->getUpdatedAtDate(),
            ],
            'league' => $domain->league
                ? ['id' => $domain->league->id, 'name' => $domain->league->name]
                : null,
            'tournaments' => $tournaments,
            'standings' => $this->mapStandingsRows($standingsPage['items']),
            'standingsHasMore' => $standingsPage['has_more'],
        ];
    }

    /**
     * @return array{items: list<array>, has_more: bool}
     */
    public function seasonStandingsPage(Season $season, int $page): array
    {
        $pageData = $this->seasonStatsService->getStandingsPage($season->id, $page);

        return [
            'items' => $this->mapStandingsRows($pageData['items']),
            'has_more' => $pageData['has_more'],
        ];
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapStandingsRows(array $rows): array
    {
        return array_map(static fn (object $row) => [
            'place' => (int) $row->place,
            'playerId' => (int) $row->player_id,
            'playerName' => (string) $row->player_name,
            'userId' => $row->user_id !== null ? (int) $row->user_id : null,
            'points' => (int) $row->points,
            'countMax' => (int) $row->count_max,
            'count170Plus' => (int) $row->count_170_plus,
            'countQf' => (int) $row->count_qf,
            'countHf' => (int) $row->count_hf,
            'bestQf' => $row->best_qf !== null ? (int) $row->best_qf : null,
            'bestHf' => $row->best_hf !== null ? (int) $row->best_hf : null,
        ], $rows);
    }

    public function tournament(Tournament $tournament): array
    {
        $viewModel = $this->getTournamentData->get($tournament->id);

        return $this->tournamentFromViewModel($viewModel);
    }

    /**
     * @return array<string, mixed>
     */
    public function tournamentFromViewModel(TournamentDataViewModel $viewModel): array
    {
        $tournament = $viewModel->tournament();
        $season = $viewModel->season();
        $league = $season?->league;

        return [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'date' => $tournament->getDate(),
                'status' => $tournament->status->value,
                'statusLabel' => $tournament->status->label(),
                'statusVariant' => $tournament->status->badgeVariant(),
                'isStarted' => $tournament->isStarted(),
                'hasPlayoff' => $tournament->hasPlayoffBracket(),
                'tracksLeaguePoints' => $tournament->tracksLeaguePoints(),
            ],
            'league' => $league
                ? ['id' => $league->id, 'name' => $league->name]
                : null,
            'season' => $season
                ? ['id' => $season->id, 'name' => $season->name]
                : null,
            'availableTabs' => $this->availableTabs($tournament),
            'results' => $this->mapResults($viewModel->results()),
            'groups' => $this->mapGroups($viewModel),
            'playoff' => $this->mapPlayoff($viewModel->playoffGames()),
            'achievements' => $this->mapAchievements($viewModel->achievements()),
        ];
    }

    /**
     * @return list<string>
     */
    private function availableTabs(TournamentDomain $tournament): array
    {
        if (! $tournament->isStarted()) {
            return [];
        }

        $tabs = ['results', 'groups', 'achievements'];
        if ($tournament->hasPlayoffBracket()) {
            array_splice($tabs, 1, 0, ['playoff']);
        }

        return $tabs;
    }

    /**
     * @param  Collection<int, array{player: mixed, place: mixed, points: mixed, stage: mixed}>  $results
     * @return list<array<string, mixed>>
     */
    private function mapResults(Collection $results): array
    {
        return $results->map(function (array $result) {
            $player = $result['player'];
            $stage = $result['stage'] ?? null;

            return [
                'place' => $result['place'] ?? null,
                'playerId' => $player?->id,
                'playerName' => $player?->name ?? '—',
                'userId' => $player?->userId,
                'points' => $result['points'] ?? null,
                'stageLabel' => $stage instanceof GameStage ? $stage->label() : null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{groupNumber: int, standings: list<array>, games: list<array>}>
     */
    private function mapGroups(TournamentDataViewModel $viewModel): array
    {
        $standingsByGroup = $viewModel->groupStandings();
        $gamesByGroup = $viewModel->games();
        $groups = [];

        foreach ($viewModel->groupNumbers() as $groupNumber) {
            $groupNumber = (int) $groupNumber;
            /** @var array<int, GroupStandingDomain> $standings */
            $standings = $standingsByGroup[$groupNumber] ?? [];
            $standingRows = collect($standings)
                ->sortBy(fn (GroupStandingDomain $s) => $s->place > 0 ? $s->place : PHP_INT_MAX)
                ->values()
                ->map(fn (GroupStandingDomain $s) => [
                    'playerId' => $s->player?->id,
                    'playerName' => $s->player?->name ?? '—',
                    'userId' => $s->player?->userId,
                    'gamesPlayed' => $s->gamesPlayed,
                    'gamesWon' => $s->gamesWon,
                    'gamesLost' => $s->gamesLost,
                    'matchUnitsDifference' => $s->matchUnitsDifference,
                    'points' => $s->points,
                    'place' => $s->place,
                ])
                ->all();

            $seenGameIds = [];
            $gameRows = [];
            foreach ($gamesByGroup[$groupNumber] ?? [] as $opponents) {
                foreach ($opponents as $game) {
                    if (! $game instanceof GroupGameDomain || isset($seenGameIds[$game->id])) {
                        continue;
                    }
                    $seenGameIds[$game->id] = true;
                    $gameRows[] = $this->mapGame($game);
                }
            }

            $groups[] = [
                'groupNumber' => $groupNumber,
                'standings' => $standingRows,
                'games' => $gameRows,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, list<PlayoffGameDomain>>  $playoffGames
     * @return list<array{round: string, roundLabel: string, games: list<array>}>
     */
    private function mapPlayoff(array $playoffGames): array
    {
        $order = [
            GameStage::SIXTEEN->value,
            GameStage::EIGHT->value,
            GameStage::QUARTER->value,
            GameStage::SEMI->value,
            GameStage::THIRD->value,
            GameStage::FINAL->value,
        ];

        $rounds = [];
        foreach ($order as $roundValue) {
            if (! isset($playoffGames[$roundValue]) || $playoffGames[$roundValue] === []) {
                continue;
            }
            $stage = GameStage::from($roundValue);
            $rounds[] = [
                'round' => $roundValue,
                'roundLabel' => $stage->label(),
                'games' => array_map(
                    fn (PlayoffGameDomain $game) => $this->mapGame($game),
                    $playoffGames[$roundValue],
                ),
            ];
        }

        return $rounds;
    }

    /**
     * @param  Collection<int, array{player: mixed, max: int, one_seventy: int, qf: list, hf: list}>  $achievements
     * @return list<array<string, mixed>>
     */
    private function mapAchievements(Collection $achievements): array
    {
        return $achievements->map(function (array $row) {
            $player = $row['player'];
            $qfValues = collect($row['qf'] ?? [])
                ->map(fn ($item) => (int) (is_object($item) ? ($item->value ?? 0) : $item))
                ->filter(fn (int $v) => $v > 0)
                ->values()
                ->all();
            $hfValues = collect($row['hf'] ?? [])
                ->map(fn ($item) => (int) (is_object($item) ? ($item->value ?? 0) : $item))
                ->filter(fn (int $v) => $v > 0)
                ->values()
                ->all();

            return [
                'playerId' => $player?->id,
                'playerName' => $player?->name ?? '—',
                'userId' => $player?->userId,
                'max' => (int) ($row['max'] ?? 0),
                'oneSeventy' => (int) ($row['one_seventy'] ?? 0),
                'qf' => $qfValues,
                'hf' => $hfValues,
            ];
        })->values()->all();
    }

    /**
     * @param  GroupGameDomain|PlayoffGameDomain  $game
     * @return array<string, mixed>
     */
    private function mapGame(GroupGameDomain|PlayoffGameDomain $game): array
    {
        return [
            'id' => $game->id,
            'player1' => [
                'id' => $game->player1?->id,
                'name' => $game->player1?->name ?? 'TBD',
                'userId' => $game->player1?->userId,
            ],
            'player2' => [
                'id' => $game->player2?->id,
                'name' => $game->player2?->name ?? 'TBD',
                'userId' => $game->player2?->userId,
            ],
            'score1' => $game->player1Score,
            'score2' => $game->player2Score,
            'status' => $game->status->value,
        ];
    }
}
