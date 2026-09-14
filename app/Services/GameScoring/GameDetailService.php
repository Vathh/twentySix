<?php

namespace App\Services\GameScoring;

use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Models\Game\Game;
use App\Models\League\LeagueGame;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\League\LeagueGameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Support\GameScoring\GameLegsSetGrouper;
use App\Support\GameScoring\GameScoringContext;
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
        private LeagueGameRepository $leagueGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private GameAuthorizationService $gameAuthorizationService,
    ) {}

    /**
     * Pełny detal (strona meczu) — wizyty + stats.
     *
     * @return array<string, mixed>
     */
    public function build(GameKind $kind, int $id): array
    {
        [$context, $game, $label, $subtitle, $backUrl] = $this->resolveDisplay($kind, $id);

        return $this->assemble($context, $game, $label, $subtitle, $backUrl, withVisitDetail: true);
    }

    /**
     * Metadane live/overlay bez wizyt. Scoring state ładuje się osobno z tego samego `$game`.
     *
     * @return array{0: array<string, mixed>, 1: GameScoringContext, 2: Game|PlayoffGame|QuickGame|LeagueGame}
     */
    public function buildShell(GameKind $kind, int $id): array
    {
        [$context, $game, $label, $subtitle, $backUrl] = $this->resolveDisplay($kind, $id);

        return [
            $this->assemble($context, $game, $label, $subtitle, $backUrl, withVisitDetail: false),
            $context,
            $game,
        ];
    }

    /**
     * @return array{0: GameScoringContext, 1: Game|PlayoffGame|QuickGame|LeagueGame, 2: string, 3: ?string, 4: string}
     */
    private function resolveDisplay(GameKind $kind, int $id): array
    {
        return match ($kind) {
            GameKind::GROUP => $this->displayFromGroupGame(
                $this->gameRepository->findModel($id, ['player1', 'player2', 'tournament.season.organization']),
            ),
            GameKind::PLAYOFF => $this->displayFromPlayoffGame(
                $this->playoffGameRepository->findModel($id, ['player1', 'player2', 'tournament.season.organization']),
            ),
            GameKind::QUICK => $this->displayFromQuickGame(
                $this->quickGameRepository->findModel($id, ['player1', 'player2']),
            ),
            GameKind::LEAGUE => $this->displayFromLeagueGame(
                $this->leagueGameRepository->findForPlay($id),
            ),
        };
    }

    /**
     * @return array{0: GameScoringContext, 1: Game, 2: string, 3: ?string, 4: string}
     */
    private function displayFromGroupGame(Game $game): array
    {
        return [
            GameScoringContext::fromGroupGame($game),
            $game,
            'Turniejowy — grupa',
            $game->tournament?->name,
            $game->tournament
                ? route('tournaments.show', ['tournament' => $game->tournament_id, 'tab' => 'groups'])
                : route('pages.home'),
        ];
    }

    /**
     * @return array{0: GameScoringContext, 1: PlayoffGame, 2: string, 3: ?string, 4: string}
     */
    private function displayFromPlayoffGame(PlayoffGame $game): array
    {
        return [
            GameScoringContext::fromPlayoffGame($game),
            $game,
            'Turniejowy — '.PlayoffRoundLabel::listLabel((string) $game->round),
            $game->tournament?->name,
            $game->tournament
                ? route('tournaments.show', ['tournament' => $game->tournament_id, 'tab' => 'playoff'])
                : route('pages.home'),
        ];
    }

    /**
     * @return array{0: GameScoringContext, 1: QuickGame, 2: string, 3: ?string, 4: string}
     */
    private function displayFromQuickGame(QuickGame $game): array
    {
        return [
            GameScoringContext::fromQuickGame($game),
            $game,
            'Towarzyski',
            'Szybki mecz',
            route('pages.home'),
        ];
    }

    /**
     * @return array{0: GameScoringContext, 1: LeagueGame, 2: string, 3: ?string, 4: string}
     */
    private function displayFromLeagueGame(LeagueGame $game): array
    {
        return [
            GameScoringContext::fromLeagueGame($game),
            $game,
            'Liga',
            $game->season?->league?->name,
            route('league-games.show', $game),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function assemble(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        string $label,
        ?string $subtitle,
        string $backUrl,
        bool $withVisitDetail,
    ): array {
        $legs = $withVisitDetail ? $this->gameLegRepository->getForContext($context) : collect();
        $legIds = $legs->pluck('id')->all();
        $visits = $withVisitDetail
            ? $this->gameVisitRepository->getActiveForGameLegs($legIds)
            : collect();
        $legStats = $withVisitDetail
            ? $this->gameLegPlayerStatRepository->getForLegIds($legIds)
            : collect();

        $openLeg = $legs->first(fn ($leg) => $leg->isOpen());

        $players = $withVisitDetail ? [
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
        ] : [];

        $legsDetail = $legs->map(function ($leg) use ($visits, $legStats) {
            $legVisits = $visits->where('game_leg_id', $leg->id);
            $stats = $legStats->where('game_leg_id', $leg->id);

            return [
                'leg' => $leg,
                'visits' => $legVisits,
                'playerStats' => $stats,
            ];
        });

        $legsBySet = $withVisitDetail
            ? GameLegsSetGrouper::group(
                $legsDetail,
                $context->matchFormat,
                $context->player1Id,
                $context->player2Id,
            )
            : [];

        $tournamentId = ($game instanceof Game || $game instanceof PlayoffGame)
            ? (int) $game->tournament_id
            : null;

        return [
            'kind' => $context->kind->value,
            'gameId' => $context->gameId,
            'label' => $label,
            'subtitle' => $subtitle,
            'backUrl' => $backUrl,
            'tournamentId' => $tournamentId,
            'groupNumber' => $game instanceof Game ? (int) $game->group_number : null,
            'playoffRound' => $game instanceof PlayoffGame ? (string) $game->round : null,
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
            'status' => $game->status instanceof \BackedEnum ? $game->status->value : (string) $game->status,
            'player1' => $game->player1,
            'player2' => $game->player2,
            'player1Score' => (int) $game->player1_score,
            'player2Score' => (int) $game->player2_score,
            'winnerId' => $game->winner_id,
            'players' => $players,
            'legsDetail' => $legsDetail,
            'legsBySet' => $legsBySet,
            'broadcastChannel' => $context->broadcastChannelName(),
            'isLive' => $game->status instanceof GameStatus
                ? $game->status === GameStatus::IN_PROGRESS
                : (string) ($game->status instanceof \BackedEnum ? $game->status->value : $game->status) === 'in_progress',
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
            'league' => GameKind::LEAGUE,
            default => throw new DomainException('Nieznany typ meczu.'),
        };
    }
}
