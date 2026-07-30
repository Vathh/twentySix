<?php

namespace App\Services\Season;

use App\Repositories\Stats\TournamentAggregateRepository;
use Illuminate\Support\Collection;

class SeasonStatsService
{
    public const STANDINGS_PER_PAGE = 50;

    public function __construct(
        private TournamentAggregateRepository $tournamentAggregateRepository,
    ) {
    }

    /**
     * Ranking sezonu: suma punktów (i achievementów) z turniejów tego sezonu.
     *
     * @return Collection<int, object{place: int, player_id: int, player_name: string, user_id: ?int, points: int, count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function getStandings(int $seasonId): Collection
    {
        $tournamentIds = $this->tournamentAggregateRepository->getTournamentIdsForSeason($seasonId);

        return $this->tournamentAggregateRepository->buildStandingsForTournaments($tournamentIds);
    }

    /**
     * Strona rankingu sezonu (po 50).
     *
     * @return array{items: list<object>, has_more: bool}
     */
    public function getStandingsPage(int $seasonId, int $page): array
    {
        $page = max(1, $page);
        $all = $this->getStandings($seasonId);
        $offset = ($page - 1) * self::STANDINGS_PER_PAGE;
        $items = $all->slice($offset, self::STANDINGS_PER_PAGE)->values();

        return [
            'items' => $items->all(),
            'has_more' => $all->count() > $offset + $items->count(),
        ];
    }
}
