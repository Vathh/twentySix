<?php

namespace App\Services\Match;

use App\Enums\AchievementType;
use App\Enums\GameStatus;
use App\Enums\MatchKind;
use App\Models\Achievements\Achievement;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Support\Match\MatchContext;
use App\Support\Match\MatchStatisticsCalculator;
use DomainException;
use Illuminate\Support\Collection;

class MatchDetailService
{
    public function __construct(
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
        private MatchAuthorizationService $matchAuthorizationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(MatchKind $kind, int $id): array
    {
        return match ($kind) {
            MatchKind::GROUP => $this->buildFromGroupGame(Game::with(['player1', 'player2', 'tournament.season.league'])->findOrFail($id)),
            MatchKind::PLAYOFF => $this->buildFromPlayoffGame(PlayoffGame::with(['player1', 'player2', 'tournament.season.league'])->findOrFail($id)),
            MatchKind::QUICK => $this->buildFromQuickGame(QuickGame::with(['player1', 'player2'])->findOrFail($id)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFromGroupGame(Game $game): array
    {
        $context = MatchContext::fromGroupGame($game);

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
        $context = MatchContext::fromPlayoffGame($game);

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
        $context = MatchContext::fromQuickGame($game);

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
        MatchContext $context,
        Game|PlayoffGame|QuickGame $match,
        string $label,
        ?string $subtitle,
        string $backUrl,
        ?int $tournamentId,
    ): array {
        $legs = $this->gameLegRepository->getForContext($context);
        $legIds = $legs->pluck('id')->all();
        $visits = $this->gameVisitRepository->getActiveForMatchLegs($legIds);
        $legStats = $this->gameLegPlayerStatRepository->getForLegIds($legIds);

        $achievements = $tournamentId
            ? Achievement::query()
                ->where('tournament_id', $tournamentId)
                ->whereIn('player_id', [$context->player1Id, $context->player2Id])
                ->get()
            : collect();

        $players = [
            $this->playerDetail($context->player1Id, $match->player1?->name ?? '—', $legStats, $achievements),
            $this->playerDetail($context->player2Id, $match->player2?->name ?? '—', $legStats, $achievements),
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
            'matchId' => $context->matchId,
            'label' => $label,
            'subtitle' => $subtitle,
            'backUrl' => $backUrl,
            'tournamentId' => $tournamentId,
            'groupNumber' => $match instanceof Game ? (int) $match->group_number : null,
            'legsToWin' => $context->legsToWin,
            'canCorrectResult' => $this->matchAuthorizationService->canCorrectTournamentMatch(
                $tournamentId,
                $context->kind,
            ),
            'status' => $match->status instanceof GameStatus ? $match->status->value : $match->status,
            'player1' => $match->player1,
            'player2' => $match->player2,
            'player1Score' => (int) $match->player1_score,
            'player2Score' => (int) $match->player2_score,
            'winnerId' => $match->winner_id,
            'players' => $players,
            'legsDetail' => $legsDetail,
            'broadcastChannel' => $context->broadcastChannelName(),
            'isLive' => $match->status === GameStatus::IN_PROGRESS,
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
            'doublePercent' => MatchStatisticsCalculator::matchDoublePercent($legStats, $playerId),
            'max' => $playerAchievements->where('type', AchievementType::MAX)->count(),
            'oneSeventy' => $playerAchievements->where('type', AchievementType::ONE_SEVENTY)->count(),
            'hf' => $playerAchievements->where('type', AchievementType::HF)->values(),
            'qf' => $playerAchievements->where('type', AchievementType::QF)->values(),
        ];
    }

    public static function kindFromRoute(string $type): MatchKind
    {
        return match ($type) {
            'group' => MatchKind::GROUP,
            'playoff' => MatchKind::PLAYOFF,
            'quick' => MatchKind::QUICK,
            default => throw new DomainException('Nieznany typ meczu.'),
        };
    }
}
