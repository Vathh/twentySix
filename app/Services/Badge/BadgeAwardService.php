<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\BadgeDetector;
use App\Domain\Badge\Checkout\CheckoutBadgeDetector;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\GameEligibility;
use App\Domain\Badge\HighVisit\HighVisitBadgeDetector;
use App\Enums\GameKind;
use App\Models\Game\Game;
use App\Models\Game\GameVisit;
use App\Models\League\LeagueGame;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\League\LeagueGameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Support\GameScoring\GameScoringContext;
use Illuminate\Support\Facades\DB;

class BadgeAwardService
{
    /** @var list<BadgeDetector> */
    private array $detectors;

    public function __construct(
        private PlayerBadgeRepository $playerBadgeRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private PlayerRepository $playerRepository,
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private LeagueGameRepository $leagueGameRepository,
        private HighVisitBadgeDetector $highVisitDetector,
        CheckoutBadgeDetector $checkoutDetector,
    ) {
        $this->detectors = [$checkoutDetector, $highVisitDetector];
    }

    public function awardForFinishedGame(
        GameScoringContext $context,
        Game|PlayoffGame|LeagueGame|QuickGame $game,
    ): void {
        if (! in_array($context->kind, [GameKind::GROUP, GameKind::PLAYOFF, GameKind::LEAGUE], true)) {
            return;
        }

        $sourceKind = $context->kind->value;
        $sourceId = $context->gameId;

        if ($this->playerBadgeRepository->hasCommit($sourceKind, $sourceId)) {
            return;
        }

        $finished = $this->buildFinishedGame($context);
        $events = [];
        if (GameEligibility::allowsCheckoutBadges($finished)) {
            foreach ($this->detectors as $detector) {
                foreach ($detector->detect($finished) as $event) {
                    $events[] = $event;
                }
            }
        }

        $this->playerBadgeRepository->insertEvents($sourceKind, $sourceId, $events);
        foreach ($events as $event) {
            $this->playerBadgeRepository->upsertFromAward(
                $event['playerId'],
                $event['category'],
                $event['badgeKey'],
                $event['amount'],
            );
        }
        $this->playerBadgeRepository->insertCommit($sourceKind, $sourceId);
    }

    /**
     * Mecze z commitem checkoutów, które nie mają jeszcze zdarzeń high_visit.
     * Nie nadaje checkoutów i nie tworzy nowego commitu.
     */
    public function backfillCommittedHighVisits(): int
    {
        $awardedGames = 0;

        foreach ($this->playerBadgeRepository->commitsMissingCategory(BadgeCategory::HighVisit->value) as $commit) {
            $context = $this->contextForSource($commit->source_kind, (int) $commit->source_id);
            if ($context === null) {
                continue;
            }

            $events = $this->highVisitDetector->detect($this->buildFinishedGame($context));
            if ($events === []) {
                continue;
            }

            DB::transaction(function () use ($commit, $events) {
                $this->playerBadgeRepository->insertEvents($commit->source_kind, (int) $commit->source_id, $events);
                foreach ($events as $event) {
                    $this->playerBadgeRepository->upsertFromAward(
                        $event['playerId'],
                        $event['category'],
                        $event['badgeKey'],
                        $event['amount'],
                    );
                }
            });
            $awardedGames++;
        }

        return $awardedGames;
    }

    public function retractForGame(GameKind $kind, int $sourceId): void
    {
        if (! in_array($kind, [GameKind::GROUP, GameKind::PLAYOFF, GameKind::LEAGUE], true)) {
            return;
        }

        $sourceKind = $kind->value;

        DB::transaction(function () use ($sourceKind, $sourceId) {
            $deleted = $this->playerBadgeRepository->deleteEventsForSource($sourceKind, $sourceId);
            $this->playerBadgeRepository->deleteCommit($sourceKind, $sourceId);

            $seen = [];
            foreach ($deleted as $row) {
                $key = $row['player_id'].'|'.$row['category'].'|'.$row['badge_key'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $totals = $this->playerBadgeRepository->remainingTotals(
                    $row['player_id'],
                    $row['category'],
                    $row['badge_key'],
                );
                $this->playerBadgeRepository->replaceTotals(
                    $row['player_id'],
                    $row['category'],
                    $row['badge_key'],
                    $totals['amount'],
                    $totals['first'],
                    $totals['last'],
                );
            }
        });
    }

    private function buildFinishedGame(GameScoringContext $context): FinishedGame
    {
        $legs = $this->gameLegRepository->getForContext($context);
        $visits = $this->gameVisitRepository->getActiveForGameLegs(
            $legs->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        $playerIds = [$context->player1Id, $context->player2Id];

        return new FinishedGame(
            kind: $context->kind,
            sourceId: $context->gameId,
            startingScore: $context->matchFormat->startingScore,
            gameType: $context->matchFormat->gameType,
            playerIds: $playerIds,
            registeredPlayerIds: $this->playerRepository->getRegisteredIds($playerIds),
            visits: $visits->map(fn (GameVisit $visit) => [
                'playerId' => (int) $visit->player_id,
                'score' => (int) $visit->score,
                'closedLeg' => (bool) $visit->closed_leg,
                'bust' => (bool) $visit->bust,
                'isVoided' => (bool) $visit->is_voided,
            ])->all(),
        );
    }

    private function contextForSource(string $sourceKind, int $sourceId): ?GameScoringContext
    {
        $kind = GameKind::tryFrom($sourceKind);

        return match ($kind) {
            GameKind::GROUP => $this->contextFromGroup($sourceId),
            GameKind::PLAYOFF => $this->contextFromPlayoff($sourceId),
            GameKind::LEAGUE => $this->contextFromLeague($sourceId),
            default => null,
        };
    }

    private function contextFromGroup(int $sourceId): ?GameScoringContext
    {
        $game = $this->gameRepository->findModelOrNull($sourceId);
        if ($game === null || ! $game->player1_id || ! $game->player2_id) {
            return null;
        }

        return GameScoringContext::fromGroupGame($game);
    }

    private function contextFromPlayoff(int $sourceId): ?GameScoringContext
    {
        $game = $this->playoffGameRepository->findModelOrNull($sourceId);
        if ($game === null || ! $game->player1_id || ! $game->player2_id) {
            return null;
        }

        return GameScoringContext::fromPlayoffGame($game);
    }

    private function contextFromLeague(int $sourceId): ?GameScoringContext
    {
        $game = $this->leagueGameRepository->findModelOrNull($sourceId);
        if ($game === null || ! $game->player1_id || ! $game->player2_id) {
            return null;
        }

        return GameScoringContext::fromLeagueGame($game);
    }
}
