<?php

namespace App\Services\GameScoring;

use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\LeagueGameStatus;
use App\Events\GameScoringCancelled;
use App\Repositories\Career\PlayerGameSnapshotRepository;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\League\LeagueGameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Services\Badge\BadgeAwardService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentPlayoffBracketLiveService;
use App\Support\GameScoring\GameScoringContext;
use App\Support\Http\DomainExceptionHttp;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GameCancelService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private LeagueGameRepository $leagueGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private PlayerGameSnapshotRepository $playerGameSnapshotRepository,
        private BadgeAwardService $badgeAwardService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
        private TournamentPlayoffBracketLiveService $playoffBracketLiveService,
    ) {}

    public function cancel(GameKind $kind, int $gameId): void
    {
        match ($kind) {
            GameKind::GROUP => $this->cancelGroup($gameId),
            GameKind::PLAYOFF => $this->cancelPlayoff($gameId),
            GameKind::LEAGUE => $this->cancelLeague($gameId),
            GameKind::QUICK => throw new DomainException(
                'Anulowanie dotyczy tylko meczów turniejowych i ligowych.',
            ),
        };
    }

    private function cancelGroup(int $gameId): void
    {
        $game = $this->gameRepository->findModel($gameId);
        $this->assertTournamentCancellable($game->status);
        $context = GameScoringContext::fromGroupGame($game);

        DB::transaction(function () use ($context, $game) {
            $this->wipeScoring($context, $game);
            $this->gameRepository->resetToScheduled($game);
        });

        $this->broadcastCancelled($context);
        $fresh = $this->gameRepository->findModelOrNull($gameId);
        if ($fresh !== null) {
            $this->groupMatrixLiveService->pushFromGroupGame($fresh, false);
        }
    }

    private function cancelPlayoff(int $gameId): void
    {
        $game = $this->playoffGameRepository->findModel($gameId);
        $this->assertTournamentCancellable($game->status);
        $context = GameScoringContext::fromPlayoffGame($game);
        $tournamentId = (int) $game->tournament_id;

        DB::transaction(function () use ($context, $game) {
            $this->wipeScoring($context, $game);
            $this->playoffGameRepository->resetToScheduled($game);
        });

        $this->broadcastCancelled($context);
        $this->playoffBracketLiveService->pushTournament($tournamentId);
    }

    private function cancelLeague(int $gameId): void
    {
        $game = $this->leagueGameRepository->findForPlay($gameId);
        $this->assertLeagueCancellable($game->status);
        $context = GameScoringContext::fromLeagueGame($game);

        DB::transaction(function () use ($context, $game) {
            $this->wipeScoring($context, $game);
            $this->leagueGameRepository->resetToScheduled($game);
        });

        $this->broadcastCancelled($context);
    }

    private function wipeScoring(GameScoringContext $context, Model $sourceable): void
    {
        $this->badgeAwardService->retractForGame($context->kind, $context->gameId);

        $legIds = $this->gameLegRepository->getForContext($context)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->gameVisitRepository->deleteForLegIds($legIds);
        $this->gameLegPlayerStatRepository->deleteForLegIds($legIds);
        $this->gameLegRepository->deleteForContext($context);
        $this->playerGameSnapshotRepository->deleteForSourceable($sourceable);
    }

    private function broadcastCancelled(GameScoringContext $context): void
    {
        $run = static function () use ($context): void {
            broadcast(new GameScoringCancelled($context));
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }

    private function assertTournamentCancellable(GameStatus $status): void
    {
        if ($status !== GameStatus::IN_PROGRESS) {
            throw new DomainException(
                'Anulować można tylko mecz w trakcie sędziowania.',
                DomainExceptionHttp::UNPROCESSABLE,
            );
        }
    }

    private function assertLeagueCancellable(LeagueGameStatus $status): void
    {
        if (! in_array($status, [LeagueGameStatus::IN_PROGRESS, LeagueGameStatus::LOBBY], true)) {
            throw new DomainException(
                'Anulować można tylko mecz w lobby albo w trakcie sędziowania.',
                DomainExceptionHttp::UNPROCESSABLE,
            );
        }
    }
}
