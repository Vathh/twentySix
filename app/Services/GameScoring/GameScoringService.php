<?php

namespace App\Services\GameScoring;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\LeagueGameStatus;
use App\Events\GameScoringStateUpdated;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
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
use App\Services\Game\GameService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Support\GameScoring\GameScoringContext;
use App\Support\GameScoring\GameStatisticsCalculator;
use App\Domain\GameScoring\MatchFormatScoring;
use App\Domain\GameScoring\VisitRecorder;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GameScoringService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private QuickGameRepository $quickGameRepository,
        private LeagueGameRepository $leagueGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private GameScoringStateBuilder $gameScoringStateBuilder,
        private GameService $gameService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
    ) {
    }

    public function resolveGroupGame(int $gameId): array
    {
        $game = $this->gameRepository->findModel($gameId, ['player1', 'player2']);
        $context = GameScoringContext::fromGroupGame($game);

        return [$context, $game];
    }

    public function resolvePlayoffGame(int $playoffGameId): array
    {
        $game = $this->playoffGameRepository->findModel($playoffGameId, ['player1', 'player2']);
        $context = GameScoringContext::fromPlayoffGame($game);

        return [$context, $game];
    }

    /**
     * @return array{0: GameScoringContext, 1: Model}
     */
    public function resolveQuickGame(int $quickGameId): array
    {
        $game = $this->quickGameRepository->findModel($quickGameId, ['player1', 'player2']);
        $context = GameScoringContext::fromQuickGame($game);

        return [$context, $game];
    }

    /**
     * @return array{0: GameScoringContext, 1: Model}
     */
    public function resolveLeagueGame(int $leagueGameId): array
    {
        $game = $this->leagueGameRepository->findForPlay($leagueGameId);
        $context = GameScoringContext::fromLeagueGame($game);

        return [$context, $game];
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(GameScoringContext $context, Game|PlayoffGame|QuickGame|LeagueGame $game): array
    {
        return $this->gameScoringStateBuilder->build($context, $game);
    }

    /**
     * @return array<string, mixed>
     */
    public function startLeg(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        bool $player1DoubleTracked,
        bool $player2DoubleTracked,
    ): array {
        if ($this->gameLegRepository->findOpenForContext($context) !== null) {
            throw new DomainException('W tym meczu jest już otwarty leg.');
        }

        if ($this->isFinished($game)) {
            throw new DomainException('Mecz jest już zakończony.');
        }

        return DB::transaction(function () use ($context, $game, $player1DoubleTracked, $player2DoubleTracked) {
            $legNumber = $this->gameLegRepository->getForContext($context)->count() + 1;
            $leg = $this->gameLegRepository->startLeg($context, $legNumber);

            $this->gameLegPlayerStatRepository->createPlaceholder(
                $leg->id,
                $context->player1Id,
                $player1DoubleTracked,
            );
            $this->gameLegPlayerStatRepository->createPlaceholder(
                $leg->id,
                $context->player2Id,
                $player2DoubleTracked,
            );

            $this->setGameInProgress($game);

            $state = $this->broadcastState($context, $game);
            $this->pushGroupMatrixLive($context, $game, includeStandings: false);

            return $state;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVisit(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        int $legId,
        RecordVisitDTO $dto,
    ): array {
        $leg = $this->resolveLegForContext($context, $legId);

        if (! $leg->isOpen()) {
            throw new DomainException('Leg jest już zamknięty.');
        }

        if (! in_array($dto->playerId, [$context->player1Id, $context->player2Id], true)) {
            throw new DomainException('Gracz nie należy do tego meczu.');
        }

        VisitRecorder::validateDto($dto, $context->startingScore());

        $existing = $this->gameVisitRepository->findByClientVisitId($dto->clientVisitId);
        if ($existing !== null) {
            if ($existing->is_voided) {
                throw new DomainException('Ta wizyta została już cofnięta.');
            }
            if ((int) $existing->game_leg_id !== (int) $leg->id) {
                throw new DomainException('Nieprawidłowa wizyta.');
            }
            $this->gameVisitRepository->updateFromDto($existing, $dto);

            return $this->broadcastState($context, $game);
        }

        return DB::transaction(function () use ($context, $game, $leg, $dto) {
            $visitNumber = $this->gameVisitRepository->nextVisitNumber($leg->id);
            $this->gameVisitRepository->create($leg->id, $visitNumber, $dto);

            return $this->broadcastState($context, $game);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function undoLastVisit(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        int $legId,
    ): array {
        $leg = $this->resolveLegForContext($context, $legId);
        $this->assertIsLatestLeg($context, $leg);

        if (
            ! $leg->isOpen()
            && $this->isFinished($game)
            && in_array($context->kind, [GameKind::GROUP, GameKind::PLAYOFF, GameKind::LEAGUE], true)
        ) {
            throw new DomainException('Cofanie wizyty po zakończeniu meczu wymaga korekty wyniku na webie.');
        }

        return DB::transaction(function () use ($context, $game, $leg) {
            $wasClosed = ! $leg->isOpen();
            $legWinnerId = $wasClosed ? (int) $leg->winner_id : null;

            $voided = $this->gameVisitRepository->voidLastForLeg($leg->id);
            if ($voided === null) {
                throw new DomainException('Brak wizyty do cofnięcia.');
            }

            if ($wasClosed) {
                $this->gameLegRepository->reopenLeg($leg->fresh());
                $this->gameLegPlayerStatRepository->resetAfterLegReopen($leg->id);
                $this->revertLegWinOnGame($game, $legWinnerId, $context);

                if ($this->isFinished($game)) {
                    $this->markInProgress($game);
                    $game->winner_id = null;
                    $this->persistGame($game);
                }
            }

            $fresh = $game->fresh(['player1', 'player2']);
            $state = $this->broadcastState($context, $fresh);
            if ($wasClosed) {
                $this->pushGroupMatrixLive($context, $fresh, includeStandings: false);
            }

            return $state;
        });
    }

    /**
     * @param  CloseLegPlayerStatsDTO[]  $playerStats
     * @return array<string, mixed>
     */
    public function closeLeg(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        int $legId,
        int $winnerId,
        array $playerStats,
    ): array {
        $leg = $this->resolveLegForContext($context, $legId);

        if (! $leg->isOpen()) {
            // Idempotent retry (tablet stracił odpowiedź) — bez ponownego finish/finalize.
            $freshGame = $game->fresh(['player1', 'player2']);

            return $this->broadcastState($context, $freshGame);
        }

        if (! in_array($winnerId, [$context->player1Id, $context->player2Id], true)) {
            throw new DomainException('Zwycięzca lega musi być uczestnikiem meczu.');
        }

        return DB::transaction(function () use ($context, $game, $leg, $winnerId, $playerStats) {
            $legVisits = $this->gameVisitRepository->getActiveForLeg($leg->id);

            foreach ($playerStats as $statsDto) {
                $playerLegVisits = $legVisits->where('player_id', $statsDto->playerId);
                $merged = $this->mergeStatsWithVisits($statsDto, $playerLegVisits);
                $this->gameLegPlayerStatRepository->updateOnLegClose($leg->id, $merged);
            }

            $p1Points = (int) $legVisits->where('player_id', $context->player1Id)->where('bust', false)->sum('score');
            $p2Points = (int) $legVisits->where('player_id', $context->player2Id)->where('bust', false)->sum('score');

            $this->gameLegRepository->finishLeg($leg, $winnerId, $p1Points, $p2Points);

            $result = MatchFormatScoring::applyLegWinToH2hGame(
                $context->matchFormat,
                $winnerId,
                $context->player1Id,
                $context->player2Id,
                (int) ($game->player1_score ?? 0),
                (int) ($game->player2_score ?? 0),
                (int) ($game->player1_legs_in_set ?? 0),
                (int) ($game->player2_legs_in_set ?? 0),
                (int) ($game->current_set_number ?? 1),
            );

            $this->applyMatchProgress($game, $result);

            if ($result['finished']) {
                $this->markFinished($game, $result['winnerId']);
            }

            $this->persistGame($game);

            $freshGame = $game->fresh(['player1', 'player2']);

            if (
                $this->isFinished($freshGame)
                && $context->tournamentId !== null
                && $context->kind !== GameKind::QUICK
                && $context->kind !== GameKind::LEAGUE
            ) {
                $this->gameService->finalizeTournamentGameFromScoring($context, $freshGame);
            }

            $state = $this->broadcastState($context, $freshGame);
            $this->pushGroupMatrixLive(
                $context,
                $freshGame,
                includeStandings: $this->isFinished($freshGame),
            );

            return $state;
        });
    }

    private function mergeStatsWithVisits(CloseLegPlayerStatsDTO $dto, $playerLegVisits): CloseLegPlayerStatsDTO
    {
        // Średnie / lotki / finish — zawsze z wizyt w DB (source of truth).
        // Client może mieć stale currentLegAverage sprzed checkoutu (np. 180 zamiast 167).
        return new CloseLegPlayerStatsDTO(
            playerId: $dto->playerId,
            doubleTracked: $dto->doubleTracked,
            doubleAttempts: $dto->doubleAttempts,
            doubleSuccesses: $dto->doubleSuccesses,
            legAverage: GameStatisticsCalculator::legAverage($playerLegVisits),
            firstNineAverage: GameStatisticsCalculator::firstNineAverage($playerLegVisits),
            highestVisit: GameStatisticsCalculator::highestVisit($playerLegVisits),
            highestFinish: GameStatisticsCalculator::highestFinish($playerLegVisits),
            dartsThrown: GameStatisticsCalculator::dartsThrown($playerLegVisits),
            checkoutDart: GameStatisticsCalculator::checkoutDart($playerLegVisits) ?? $dto->checkoutDart,
        );
    }

    private function assertIsLatestLeg(GameScoringContext $context, GameLeg $leg): void
    {
        $latestLegNumber = (int) $this->gameLegRepository->getForContext($context)->max('leg_number');

        if ((int) $leg->leg_number !== $latestLegNumber) {
            throw new DomainException('Można cofnąć tylko ostatni leg meczu.');
        }
    }

    private function revertLegWinOnGame(Game|PlayoffGame|QuickGame|LeagueGame $game, ?int $legWinnerId, GameScoringContext $context): void
    {
        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            $context->matchFormat,
            $legWinnerId,
            $context->player1Id,
            $context->player2Id,
            (int) ($game->player1_score ?? 0),
            (int) ($game->player2_score ?? 0),
            (int) ($game->player1_legs_in_set ?? 0),
            (int) ($game->player2_legs_in_set ?? 0),
            (int) ($game->current_set_number ?? 1),
        );

        $this->applyMatchProgress($game, $result);

        $this->persistGame($game);
    }

    private function resolveLegForContext(GameScoringContext $context, int $legId): GameLeg
    {
        $leg = $this->gameLegRepository->findModel($legId);

        $belongs = match ($context->kind) {
            GameKind::GROUP => (int) $leg->game_id === $context->gameId,
            GameKind::PLAYOFF => (int) $leg->playoff_game_id === $context->gameId,
            GameKind::QUICK => (int) $leg->quick_game_id === $context->gameId,
            GameKind::LEAGUE => (int) $leg->league_game_id === $context->gameId,
        };

        if (! $belongs) {
            throw new DomainException('Leg nie należy do tego meczu.');
        }

        return $leg;
    }

    private function setGameInProgress(Game|PlayoffGame|QuickGame|LeagueGame $game): void
    {
        if ($this->isFinished($game)) {
            return;
        }

        $this->markInProgress($game);
        $this->persistGame($game);
    }

    /**
     * @param  array{player1Score: int, player2Score: int, player1LegsInSet: int, player2LegsInSet: int, currentSetNumber: int}  $result
     */
    private function applyMatchProgress(Game|PlayoffGame|QuickGame|LeagueGame $game, array $result): void
    {
        $game->player1_score = $result['player1Score'];
        $game->player2_score = $result['player2Score'];
        if ($game instanceof LeagueGame) {
            return;
        }

        $game->player1_legs_in_set = $result['player1LegsInSet'];
        $game->player2_legs_in_set = $result['player2LegsInSet'];
        $game->current_set_number = $result['currentSetNumber'];
    }

    private function isFinished(Game|PlayoffGame|QuickGame|LeagueGame $game): bool
    {
        if ($game instanceof LeagueGame) {
            return $game->status === LeagueGameStatus::FINISHED;
        }

        return $game->status === GameStatus::FINISHED;
    }

    private function markFinished(Game|PlayoffGame|QuickGame|LeagueGame $game, ?int $winnerId): void
    {
        $game->status = $game instanceof LeagueGame ? LeagueGameStatus::FINISHED : GameStatus::FINISHED;
        $game->winner_id = $winnerId;
    }

    private function markInProgress(Game|PlayoffGame|QuickGame|LeagueGame $game): void
    {
        $game->status = $game instanceof LeagueGame ? LeagueGameStatus::IN_PROGRESS : GameStatus::IN_PROGRESS;
    }

    private function persistGame(Game|PlayoffGame|QuickGame|LeagueGame $game): void
    {
        match (true) {
            $game instanceof Game => $this->gameRepository->save($game),
            $game instanceof PlayoffGame => $this->playoffGameRepository->save($game),
            $game instanceof QuickGame => $this->quickGameRepository->save($game),
            $game instanceof LeagueGame => $this->leagueGameRepository->save($game),
        };
    }

    private function pushGroupMatrixLive(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame|LeagueGame $game,
        bool $includeStandings,
    ): void {
        if ($context->kind !== GameKind::GROUP || ! $game instanceof Game) {
            return;
        }

        $this->groupMatrixLiveService->pushFromGroupGameAfterCommit($game, $includeStandings);
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcastState(GameScoringContext $context, Game|PlayoffGame|QuickGame|LeagueGame $game): array
    {
        $game->loadMissing(['player1', 'player2']);
        $state = $this->gameScoringStateBuilder->build($context, $game);
        broadcast(new GameScoringStateUpdated($context, $state));

        return $state;
    }
}
