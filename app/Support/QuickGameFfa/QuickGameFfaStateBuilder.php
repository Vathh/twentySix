<?php

namespace App\Support\QuickGameFfa;

use App\Support\GameScoring\VisitRecorder;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Support\GameScoring\ScoringStateContract;
use Illuminate\Support\Collection;

class QuickGameFfaStateBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(QuickGameFfaSession $session, Collection $activeVisits, ?int $currentUserId = null, ?array $presence = null): array
    {
        $playerIds = $session->player_order ?? [];
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');
        $legsWon = VisitRecorder::countLegsWon($activeVisits, $playerIds);
        $currentLegVisits = $activeVisits->where('leg_number', $session->current_leg_number);
        $allLegGroups = $activeVisits->groupBy('leg_number');

        $playerStates = [];
        foreach ($playerIds as $orderIndex => $playerId) {
            $player = $players->get($playerId);
            $legVisits = $currentLegVisits->where('player_id', $playerId);
            $remaining = VisitRecorder::remainingFromLegVisits($legVisits, (int) $session->starting_score);
            $playerAll = $activeVisits
                ->where('player_id', $playerId)
                ->where('bust', false);
            $legByLegScores = [];
            $legsAverages = [];
            $dartsPerLeg = [];

            foreach ($allLegGroups as $legNum => $legAllVisits) {
                if ($session->isInProgress() && (int) $legNum === (int) $session->current_leg_number) {
                    continue;
                }

                $playerLegVisits = $legAllVisits
                    ->where('player_id', $playerId)
                    ->where('bust', false);
                if ($playerLegVisits->isEmpty()) {
                    continue;
                }

                $legByLegScores[] = $playerLegVisits->pluck('score')->values()->all();
                $avg = $this->legAverage($playerLegVisits);
                if ($avg !== null) {
                    $legsAverages[] = $avg;
                }

                if (VisitRecorder::legWinnerPlayerId($legAllVisits) === (int) $playerId) {
                    $dartsPerLeg[] = (int) ($playerLegVisits->sum('darts_in_visit') ?: ($playerLegVisits->count() * 3));
                }
            }

            $playerStates[] = [
                'playerId' => (int) $playerId,
                'name' => $player?->name ?? 'Gracz',
                'orderIndex' => $orderIndex,
                'legsWon' => $legsWon[$playerId] ?? 0,
                'remaining' => $remaining,
                'legAverage' => $this->legAverage($legVisits),
                'gameAverage' => $this->gameAverage($activeVisits, (int) $playerId, $session->starting_score),
                'legByLegScores' => $legByLegScores,
                'legsAverages' => $legsAverages,
                'dartsPerLeg' => $dartsPerLeg,
                'matchDartsThrown' => (int) ($playerAll->sum('darts_in_visit') ?: ($playerAll->count() * 3)),
                'matchPointsEarned' => (int) $playerAll->sum('score'),
            ];
        }

        $out = [
            'session' => [
                'id' => $session->id,
                'lobbyId' => $session->lobby_id,
                'status' => $session->status,
                'legsToWin' => (int) $session->legs_to_win,
                'gameType' => $session->game_type,
                'scoringMode' => $session->scoring_mode,
                'startingScore' => (int) $session->starting_score,
                'currentLegNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'stateVersion' => (int) $session->state_version,
                'quickGameId' => $session->quick_game_id,
            ],
            'players' => $playerStates,
            'currentLeg' => [
                'legNumber' => (int) $session->current_leg_number,
                'open' => $session->isInProgress(),
                'openerPlayerId' => $playerIds[$session->leg_opener_index] ?? null,
            ],
            'visits' => $currentLegVisits->map(fn ($v) => [
                'id' => $v->id,
                'playerId' => $v->player_id,
                'visitNumber' => $v->visit_number,
                'score' => $v->score,
                'remainingBefore' => $v->remaining_before,
                'remainingAfter' => $v->remaining_after,
                'dartsInVisit' => $v->darts_in_visit,
                'closedLeg' => $v->closed_leg,
                'bust' => $v->bust,
            ])->values()->all(),
            'game' => [
                'status' => $session->status === QuickGameFfaSession::STATUS_FINISHED ? 'finished' : 'in_progress',
                'legsToWin' => (int) $session->legs_to_win,
            ],
        ];

        if ($currentUserId !== null) {
            $lobby = $session->lobby;
            if ($lobby) {
                $out['youAreHost'] = (int) $lobby->host_id === $currentUserId;
            }
            $myIndex = $this->resolveMyIndex($playerIds, $currentUserId);
            if ($myIndex !== null) {
                $out['myPlayerIndex'] = $myIndex;
            }
        }

        if ($presence !== null) {
            $out['presence'] = $presence;
        }

        return ScoringStateContract::enrichFfa($out);
    }

    private function legAverage(Collection $legVisits): ?float
    {
        $scored = $legVisits->where('bust', false)->where('score', '>', 0);
        if ($scored->isEmpty()) {
            return null;
        }
        $total = $scored->sum('score');
        $darts = $scored->sum('darts_in_visit') ?: ($scored->count() * 3);

        return $darts > 0 ? round(($total / $darts) * 3, 2) : null;
    }

    private function gameAverage(Collection $allVisits, int $playerId, int $startingScore): ?float
    {
        $playerVisits = $allVisits->where('player_id', $playerId)->where('bust', false);
        if ($playerVisits->isEmpty()) {
            return null;
        }
        $totalScore = $playerVisits->sum('score');
        $darts = $playerVisits->sum('darts_in_visit') ?: ($playerVisits->count() * 3);

        return $darts > 0 ? round(($totalScore / $darts) * 3, 2) : null;
    }

    /**
     * @param  array<int, int>  $playerIds
     */
    private function resolveMyIndex(array $playerIds, int $userId): ?int
    {
        $player = Player::where('user_id', $userId)->first();
        if (! $player) {
            return null;
        }
        $idx = array_search((int) $player->id, array_map('intval', $playerIds), true);

        return $idx === false ? null : (int) $idx;
    }
}
