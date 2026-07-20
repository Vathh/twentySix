<?php

namespace App\Services\GameScoring;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Enums\GameStatus;
use App\Enums\GameKind;
use App\Events\GameScoringStateUpdated;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Services\Game\GameService;
use App\Support\GameScoring\GameScoringContext;
use App\Support\GameScoring\GameStatisticsCalculator;
use App\Support\GameScoring\MatchFormatScoring;
use App\Support\GameScoring\VisitRecorder;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GameScoringService
{
    public function __construct(
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private GameScoringStateBuilder $gameScoringStateBuilder,
        private GameService $gameService,
    ) {
    }

    public function resolveGroupGame(int $gameId): array
    {
        $game = Game::with(['player1', 'player2'])->findOrFail($gameId);
        $context = GameScoringContext::fromGroupGame($game);

        return [$context, $game];
    }

    public function resolvePlayoffGame(int $playoffGameId): array
    {
        $game = PlayoffGame::with(['player1', 'player2'])->findOrFail($playoffGameId);
        $context = GameScoringContext::fromPlayoffGame($game);

        return [$context, $game];
    }

    /**
     * @return array{0: GameScoringContext, 1: Model}
     */
    public function resolveQuickGame(int $quickGameId): array
    {
        $game = QuickGame::with(['player1', 'player2'])->findOrFail($quickGameId);
        $context = GameScoringContext::fromQuickGame($game);

        return [$context, $game];
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(GameScoringContext $context, Game|PlayoffGame|QuickGame $game): array
    {
        return $this->gameScoringStateBuilder->build($context, $game);
    }

    /**
     * @return array<string, mixed>
     */
    public function startLeg(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame $game,
        bool $player1DoubleTracked,
        bool $player2DoubleTracked,
    ): array {
        if ($this->gameLegRepository->findOpenForContext($context) !== null) {
            throw new DomainException('W tym meczu jest już otwarty leg.');
        }

        if ($game->status === GameStatus::FINISHED) {
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

            return $this->broadcastState($context, $game);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVisit(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame $game,
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
        Game|PlayoffGame|QuickGame $game,
        int $legId,
    ): array {
        $leg = $this->resolveLegForContext($context, $legId);
        $this->assertIsLatestLeg($context, $leg);

        if (
            ! $leg->isOpen()
            && $game->status === GameStatus::FINISHED
            && $context->tournamentId !== null
            && $context->kind !== GameKind::QUICK
        ) {
            throw new DomainException('Cofanie wizyty po zakończeniu meczu turniejowego wymaga korekty wyniku na webie.');
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

                if ($game->status === GameStatus::FINISHED) {
                    $game->status = GameStatus::IN_PROGRESS;
                    $game->winner_id = null;
                    $game->save();
                }
            }

            return $this->broadcastState($context, $game->fresh(['player1', 'player2']));
        });
    }

    /**
     * @param  CloseLegPlayerStatsDTO[]  $playerStats
     * @return array<string, mixed>
     */
    public function closeLeg(
        GameScoringContext $context,
        Game|PlayoffGame|QuickGame $game,
        int $legId,
        int $winnerId,
        array $playerStats,
    ): array {
        $leg = $this->resolveLegForContext($context, $legId);

        if (! $leg->isOpen()) {
            throw new DomainException('Leg jest już zamknięty.');
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

            $game->player1_score = (int) ($game->player1_score ?? 0);
            $game->player2_score = (int) ($game->player2_score ?? 0);

            $matchFinished = MatchFormatScoring::applyLegWinToH2hGame(
                $game,
                $context->matchFormat,
                $winnerId,
                $context->player1Id,
                $context->player2Id,
            );

            if ($matchFinished) {
                $game->status = GameStatus::FINISHED;
            }

            $game->save();

            $freshGame = $game->fresh(['player1', 'player2']);

            if (
                $freshGame->status === GameStatus::FINISHED
                && $context->tournamentId !== null
                && $context->kind !== GameKind::QUICK
            ) {
                $this->gameService->finalizeTournamentGameFromScoring($context, $freshGame);
            }

            return $this->broadcastState($context, $freshGame);
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

    private function revertLegWinOnGame(Game|PlayoffGame|QuickGame $game, ?int $legWinnerId, GameScoringContext $context): void
    {
        MatchFormatScoring::revertLegWinOnH2hGame(
            $game,
            $context->matchFormat,
            $legWinnerId,
            $context->player1Id,
            $context->player2Id,
        );
        $game->save();
    }

    private function resolveLegForContext(GameScoringContext $context, int $legId): GameLeg
    {
        $leg = GameLeg::findOrFail($legId);

        $belongs = match ($context->kind) {
            GameKind::GROUP => (int) $leg->game_id === $context->gameId,
            GameKind::PLAYOFF => (int) $leg->playoff_game_id === $context->gameId,
            GameKind::QUICK => (int) $leg->quick_game_id === $context->gameId,
        };

        if (! $belongs) {
            throw new DomainException('Leg nie należy do tego meczu.');
        }

        return $leg;
    }

    private function setGameInProgress(Game|PlayoffGame|QuickGame $game): void
    {
        if ($game->status !== GameStatus::FINISHED) {
            $game->status = GameStatus::IN_PROGRESS;
            $game->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcastState(GameScoringContext $context, Game|PlayoffGame|QuickGame $game): array
    {
        $game->loadMissing(['player1', 'player2']);
        $state = $this->gameScoringStateBuilder->build($context, $game);
        broadcast(new GameScoringStateUpdated($context, $state));

        return $state;
    }
}
