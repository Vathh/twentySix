<?php

namespace App\Services\GameScoring;

use App\Enums\AchievementType;
use App\Enums\GameStatus;
use App\Enums\GameKind;
use App\Models\Achievements\Achievement;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Support\GameScoring\GameScoringContext;
use App\Support\GameScoring\GameStatisticsCalculator;
use DomainException;
use Illuminate\Support\Collection;

class GameDetailService
{
    public function __construct(
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
            GameKind::GROUP => $this->buildFromGroupGame(Game::with(['player1', 'player2', 'tournament.season.league'])->findOrFail($id)),
            GameKind::PLAYOFF => $this->buildFromPlayoffGame(PlayoffGame::with(['player1', 'player2', 'tournament.season.league'])->findOrFail($id)),
            GameKind::QUICK => $this->buildFromQuickGame(QuickGame::with(['player1', 'player2'])->findOrFail($id)),
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
            tournamentId: $game->tournament_id,
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
            label: 'Turniejowy — '.$game->round->label(),
            subtitle: $game->tournament?->name,
            backUrl: $game->tournament
                ? route('tournaments.show', ['tournament' => $game->tournament_id, 'tab' => 'playoff'])
                : route('pages.home'),
            tournamentId: $game->tournament_id,
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
            tournamentId: null,
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
        ?int $tournamentId,
    ): array {
        $legs = $this->gameLegRepository->getForContext($context);
        $legIds = $legs->pluck('id')->all();
        $visits = $this->gameVisitRepository->getActiveForGameLegs($legIds);
        $legStats = $this->gameLegPlayerStatRepository->getForLegIds($legIds);

        $achievements = $tournamentId
            ? Achievement::query()
                ->where('tournament_id', $tournamentId)
                ->whereIn('player_id', [$context->player1Id, $context->player2Id])
                ->get()
            : collect();

        $players = [
            $this->playerDetail($context->player1Id, $game->player1?->name ?? '—', $legStats, $achievements),
            $this->playerDetail($context->player2Id, $game->player2?->name ?? '—', $legStats, $achievements),
        ];

        $legsDetail = $legs->map(function ($leg) use ($visits, $legStats) {
            $legVisits = $visits->where('game_leg_id', $leg->id);
            $stats = $legStats->where('game_leg_id', $leg->id);

            return [
                'leg' => $leg,
                'visits' => $legVisits,
                'playerStats' => $stats,
            ];
        });

        return [
            'kind' => $context->kind->value,
            'gameId' => $context->gameId,
            'label' => $label,
            'subtitle' => $subtitle,
            'backUrl' => $backUrl,
            'tournamentId' => $tournamentId,
            'groupNumber' => $game instanceof Game ? (int) $game->group_number : null,
            'legsToWin' => $context->legsToWin,
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
            'broadcastChannel' => $context->broadcastChannelName(),
            'isLive' => $game->status === GameStatus::IN_PROGRESS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerDetail(int $playerId, string $name, Collection $legStats, Collection $achievements): array
    {
        $playerAchievements = $achievements->where('player_id', $playerId);

        return [
            'id' => $playerId,
            'name' => $name,
            'doublePercent' => GameStatisticsCalculator::gameDoublePercent($legStats, $playerId),
            'max' => $playerAchievements->where('type', AchievementType::MAX)->count(),
            'oneSeventy' => $playerAchievements->where('type', AchievementType::ONE_SEVENTY)->count(),
            'hf' => $playerAchievements->where('type', AchievementType::HF)->values(),
            'qf' => $playerAchievements->where('type', AchievementType::QF)->values(),
        ];
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
