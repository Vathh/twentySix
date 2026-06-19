<?php

namespace App\Services\GameScoring;

use App\Enums\GameStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Support\GameScoring\GameScoringContext;
use App\Support\GameScoring\GameStatisticsCalculator;
use App\Support\GameScoring\ScoringStateContract;

class GameScoringStateBuilder
{
    public function __construct(
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
        private GameLegPlayerStatRepository $gameLegPlayerStatRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(GameScoringContext $context, Game|PlayoffGame|QuickGame $game): array
    {
        $legs = $this->gameLegRepository->getForContext($context);
        $legIds = $legs->pluck('id')->all();
        $allVisits = $this->gameVisitRepository->getActiveForGameLegs($legIds);
        $allLegStats = $this->gameLegPlayerStatRepository->getForLegIds($legIds);

        $openLeg = $legs->first(fn (GameLeg $leg) => $leg->isOpen());

        $player1LegsWon = (int) ($game->player1_score ?? 0);
        $player2LegsWon = (int) ($game->player2_score ?? 0);

        $players = [
            $this->buildPlayerState(
                $context->player1Id,
                $game->player1?->name ?? 'Gracz 1',
                $openLeg,
                $allVisits,
                $allLegStats,
                $context,
                $player1LegsWon,
            ),
            $this->buildPlayerState(
                $context->player2Id,
                $game->player2?->name ?? 'Gracz 2',
                $openLeg,
                $allVisits,
                $allLegStats,
                $context,
                $player2LegsWon,
            ),
        ];

        return ScoringStateContract::enrichH2h([
            'game' => [
                'id' => $context->gameId,
                'kind' => $context->kind->value,
                'status' => $game->status instanceof GameStatus ? $game->status->value : $game->status,
                'tournamentId' => $context->tournamentId,
                'legsToWin' => $context->legsToWin,
                'player1LegsWon' => $player1LegsWon,
                'player2LegsWon' => $player2LegsWon,
                'startingScore' => $context->startingScore,
            ],
            'players' => $players,
            'currentLeg' => $openLeg ? [
                'id' => $openLeg->id,
                'legNumber' => $openLeg->leg_number,
                'open' => true,
            ] : null,
            'legs' => $legs->whereNotNull('finished_at')->map(fn (GameLeg $leg) => [
                'id' => $leg->id,
                'legNumber' => $leg->leg_number,
                'winnerId' => $leg->winner_id,
                'finishedAt' => $leg->finished_at?->toIso8601String(),
            ])->values()->all(),
            'visits' => $openLeg
                ? $allVisits->where('game_leg_id', $openLeg->id)->map(fn ($v) => [
                    'id' => $v->id,
                    'playerId' => $v->player_id,
                    'visitNumber' => $v->visit_number,
                    'score' => $v->score,
                    'remainingBefore' => $v->remaining_before,
                    'remainingAfter' => $v->remaining_after,
                    'dartsInVisit' => $v->darts_in_visit,
                    'closedLeg' => $v->closed_leg,
                    'bust' => $v->bust,
                ])->values()->all()
                : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlayerState(
        int $playerId,
        string $name,
        ?GameLeg $openLeg,
        $allVisits,
        $allLegStats,
        GameScoringContext $context,
        int $legsWon,
    ): array {
        $gameVisits = $allVisits->where('player_id', $playerId);
        $openLegVisits = $openLeg
            ? $gameVisits->where('game_leg_id', $openLeg->id)
            : collect();

        $remaining = $context->startingScore;
        if ($openLegVisits->isNotEmpty()) {
            $last = $openLegVisits->sortByDesc('visit_number')->sortByDesc('id')->first();
            $remaining = (int) $last->remaining_after;
        }

        return [
            'playerId' => $playerId,
            'name' => $name,
            'legsWon' => $legsWon,
            'remaining' => $remaining,
            'legAverage' => GameStatisticsCalculator::legAverage($openLegVisits),
            'gameAverage' => GameStatisticsCalculator::gameAverage($gameVisits),
            'firstNineAverage' => GameStatisticsCalculator::firstNineAverage($openLegVisits),
            'doublePercent' => GameStatisticsCalculator::gameDoublePercent($allLegStats, $playerId),
        ];
    }
}
