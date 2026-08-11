<?php

namespace App\Services\GameScoring;

use App\Enums\GameStatus;
use App\Enums\GameKind;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Support\GameScoring\GameScoringContext;
use App\Support\GameScoring\GameLegsSetGrouper;
use App\Support\GameScoring\GameStatisticsCalculator;
use App\Support\Tournament\PlayoffRoundLabel;
use DomainException;
use Illuminate\Support\Collection;

class GameDetailService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private QuickGameRepository $quickGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private GameAuthorizationService $gameAuthorizationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(GameKind $kind, int $id): array
    {
        return match ($kind) {
            GameKind::GROUP => $this->buildFromGroupGame(
                $this->gameRepository->findModel($id, ['player1', 'player2', 'tournament.season.league']),
            ),
            GameKind::PLAYOFF => $this->buildFromPlayoffGame(
                $this->playoffGameRepository->findModel($id, ['player1', 'player2', 'tournament.season.league']),
            ),
            GameKind::QUICK => $this->buildFromQuickGame(
                $this->quickGameRepository->findModel($id, ['player1', 'player2']),
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromGroupGame(Game $game): array
    {
        $context = GameScoringContext::fromGroupGame($game);

        return $this->assemble(
            $context,
            $game,
            label: 'Turniejowy — grupa',
            subtitle: $game->tournament?->name,
            backUrl: $game->tournament
                ? route('tournaments.show', ['tournament' => $game->tournament_id, 'tab' => 'groups'])
                : route('pages.home'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromPlayoffGame(PlayoffGame $game): array
    {
        $context = GameScoringContext::fromPlayoffGame($game);

        return $this->assemble(
            $context,
            $game,
            label: 'Turniejowy — '.PlayoffRoundLabel::label((string) $game->round),
            subtitle: $game->tournament?->name,
            backUrl: $game->tournament
                ? route('tournaments.show', ['tournament' => $game->tournament_id, 'tab' => 'playoff'])
                : route('pages.home'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromQuickGame(QuickGame $game): array
    {
        $context = GameScoringContext::fromQuickGame($game);

        return $this->assemble(
            $context,
            $game,
            label: 'Towarzyski',
            subtitle: 'Szybki mecz',
            backUrl: route('pages.home'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame $game,
        string $label,
        ?string $subtitle,
        string $backUrl,
    ): array {
        $legs = $this->gameLegRepository->getForContext($context);
        $legIds = $legs->pluck('id')->all();
        $visits = $this->gameVisitRepository->getActiveForGameLegs($legIds);
        $legStats = $this->gameLegPlayerStatRepository->getForLegIds($legIds);

        $openLeg = $legs->first(fn ($leg) => $leg->isOpen());

        $players = [
            $this->playerDetail(
                $context->player1Id,
                $game->player1?->name ?? '—',
                $legStats,
                $visits,
                $legs,
                $openLeg?->id,
            ),
            $this->playerDetail(
                $context->player2Id,
                $game->player2?->name ?? '—',
                $legStats,
                $visits,
                $legs,
                $openLeg?->id,
            ),
        ];

        $legsDetail = $legs->map(function ($leg) use ($visits, $legStats) {
            $legVisits = $visits->where('game_leg_id', $leg->id);
            $stats = $legStats->where('game_leg_id', $leg->id)->map(function ($stat) use ($legVisits) {
                $playerVisits = $legVisits->where('player_id', $stat->player_id);

                $stat->leg_average = GameStatisticsCalculator::legAverage($playerVisits);
                $stat->first_nine_average = GameStatisticsCalculator::firstNineAverage($playerVisits);
                $stat->highest_visit = GameStatisticsCalculator::highestVisit($playerVisits);
                $stat->highest_finish = GameStatisticsCalculator::highestFinish($playerVisits);
                $stat->darts_thrown = GameStatisticsCalculator::dartsThrown($playerVisits);

                return $stat;
            });

            return [
                'leg' => $leg,
                'visits' => $legVisits,
                'playerStats' => $stats,
            ];
        });

        $legsBySet = GameLegsSetGrouper::group(
            $legsDetail,
            $context->matchFormat,
            $context->player1Id,
            $context->player2Id,
        );

        $tournamentId = $game instanceof QuickGame
            ? null
            : ($game->tournament_id !== null ? (int) $game->tournament_id : null);

        return [
            'kind' => $context->kind->value,
            'gameId' => $context->gameId,
            'label' => $label,
            'subtitle' => $subtitle,
            'backUrl' => $backUrl,
            'tournamentId' => $tournamentId,
            'groupNumber' => $game instanceof Game ? (int) $game->group_number : null,
            'matchFormat' => $context->matchFormat->toArray(),
            'formatLabel' => $context->matchFormat->formatLabel(),
            'scoreUnit' => $context->matchFormat->scoreUnit(),
            'scoreToWin' => $context->matchFormat->scoreToWin(),
            'walkoverScoreLine' => $context->matchFormat->walkoverScoreLine(),
            'usesSetScore' => $context->matchFormat->usesSetScore(),
            'canCorrectResult' => $this->gameAuthorizationService->canCorrectTournamentGame(
                $tournamentId,
                $context->kind,
            ),
            'status' => $game->status instanceof GameStatus ? $game->status->value : $game->status,
            'player1' => $game->player1,
            'player2' => $game->player2,
            'player1Score' => (int) $game->player1_score,
            'player2Score' => (int) $game->player2_score,
            'winnerId' => $game->winner_id,
            'players' => $players,
            'legsDetail' => $legsDetail,
            'legsBySet' => $legsBySet,
            'broadcastChannel' => $context->broadcastChannelName(),
            'isLive' => $game->status === GameStatus::IN_PROGRESS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerDetail(
        int $playerId,
        string $name,
        Collection $legStats,
        Collection $visits,
        Collection $legs,
        ?int $openLegId,
    ): array {
        return array_merge(
            GameStatisticsCalculator::playerMatchStats($visits, $legs, $legStats, $playerId, $openLegId),
            [
                'id' => $playerId,
                'name' => $name,
                'hf' => $this->highFinishesInGame($visits, $playerId),
                'qf' => $this->quickFinishesInGame($legs, $legStats, $playerId),
            ],
        );
    }

    /**
     * HF w tym meczu: checkouty ≥ 100.
     *
     * @return list<int>
     */
    private function highFinishesInGame(Collection $visits, int $playerId): array
    {
        return $visits
            ->where('player_id', $playerId)
            ->where('bust', false)
            ->where('closed_leg', true)
            ->filter(fn ($v) => (int) $v->score >= 100)
            ->map(fn ($v) => (int) $v->score)
            ->values()
            ->all();
    }

    /**
     * QF w tym meczu: wygrane legi w mniej niż 20 lotek.
     *
     * @return list<int>
     */
    private function quickFinishesInGame(Collection $legs, Collection $legStats, int $playerId): array
    {
        $wonLegIds = $legs
            ->whereNotNull('finished_at')
            ->where('winner_id', $playerId)
            ->pluck('id')
            ->all();

        if ($wonLegIds === []) {
            return [];
        }

        return $legStats
            ->where('player_id', $playerId)
            ->whereIn('game_leg_id', $wonLegIds)
            ->filter(fn ($s) => $s->darts_thrown !== null && (int) $s->darts_thrown < 20)
            ->map(fn ($s) => (int) $s->darts_thrown)
            ->sort()
            ->values()
            ->all();
    }

    public static function kindFromRoute(string $type): GameKind
    {
        return match ($type) {
            'group' => GameKind::GROUP,
            'playoff' => GameKind::PLAYOFF,
            'quick' => GameKind::QUICK,
            default => throw new DomainException('Nieznany typ meczu.'),
        };
    }
}
