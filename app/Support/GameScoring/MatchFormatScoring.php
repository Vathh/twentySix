<?php

namespace App\Support\GameScoring;

use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;

final class MatchFormatScoring
{
    /**
     * @return bool true when match is finished
     */
    public static function applyLegWinToH2hGame(
        Game|PlayoffGame|QuickGame $game,
        MatchFormat $format,
        int $winnerPlayerId,
        int $player1Id,
        int $player2Id,
    ): bool {
        if ($format->isSingleSet()) {
            if ($winnerPlayerId === $player1Id) {
                $game->player1_score = (int) ($game->player1_score ?? 0) + 1;
            } else {
                $game->player2_score = (int) ($game->player2_score ?? 0) + 1;
            }

            $p1 = (int) $game->player1_score;
            $p2 = (int) $game->player2_score;

            if ($p1 >= $format->legsToWinSet || $p2 >= $format->legsToWinSet) {
                $game->winner_id = $p1 >= $format->legsToWinSet ? $player1Id : $player2Id;

                return true;
            }

            return false;
        }

        if ($winnerPlayerId === $player1Id) {
            $game->player1_legs_in_set = (int) ($game->player1_legs_in_set ?? 0) + 1;
        } else {
            $game->player2_legs_in_set = (int) ($game->player2_legs_in_set ?? 0) + 1;
        }

        $p1Legs = (int) $game->player1_legs_in_set;
        $p2Legs = (int) $game->player2_legs_in_set;

        if ($p1Legs < $format->legsToWinSet && $p2Legs < $format->legsToWinSet) {
            return false;
        }

        if ($p1Legs >= $format->legsToWinSet) {
            $game->player1_score = (int) ($game->player1_score ?? 0) + 1;
        } else {
            $game->player2_score = (int) ($game->player2_score ?? 0) + 1;
        }

        $game->player1_legs_in_set = 0;
        $game->player2_legs_in_set = 0;
        $game->current_set_number = (int) ($game->current_set_number ?? 1) + 1;

        $p1Sets = (int) $game->player1_score;
        $p2Sets = (int) $game->player2_score;

        if ($p1Sets >= $format->setsToWinMatch || $p2Sets >= $format->setsToWinMatch) {
            $game->winner_id = $p1Sets >= $format->setsToWinMatch ? $player1Id : $player2Id;

            return true;
        }

        return false;
    }

    public static function revertLegWinOnH2hGame(
        Game|PlayoffGame|QuickGame $game,
        MatchFormat $format,
        ?int $legWinnerId,
        int $player1Id,
        int $player2Id,
    ): void {
        if ($legWinnerId === null) {
            return;
        }

        if ($format->isSingleSet()) {
            if ((int) $game->player1_id === $legWinnerId && (int) ($game->player1_score ?? 0) > 0) {
                $game->player1_score--;
            } elseif ((int) $game->player2_id === $legWinnerId && (int) ($game->player2_score ?? 0) > 0) {
                $game->player2_score--;
            }

            return;
        }

        $p1Legs = (int) ($game->player1_legs_in_set ?? 0);
        $p2Legs = (int) ($game->player2_legs_in_set ?? 0);
        $setWasClosed = $p1Legs === 0 && $p2Legs === 0
            && ((int) ($game->player1_score ?? 0) > 0 || (int) ($game->player2_score ?? 0) > 0);

        if ($setWasClosed) {
            if ((int) $game->player1_id === $legWinnerId && (int) ($game->player1_score ?? 0) > 0) {
                $game->player1_score--;
                $game->player1_legs_in_set = $format->legsToWinSet - 1;
            } elseif ((int) $game->player2_id === $legWinnerId && (int) ($game->player2_score ?? 0) > 0) {
                $game->player2_score--;
                $game->player2_legs_in_set = $format->legsToWinSet - 1;
            }

            if ((int) ($game->current_set_number ?? 1) > 1) {
                $game->current_set_number = (int) $game->current_set_number - 1;
            }

            return;
        }

        if ((int) $game->player1_id === $legWinnerId && $p1Legs > 0) {
            $game->player1_legs_in_set = $p1Legs - 1;
        } elseif ((int) $game->player2_id === $legWinnerId && $p2Legs > 0) {
            $game->player2_legs_in_set = $p2Legs - 1;
        }
    }

    /**
     * @param  array<int, int>  $legsWonInSet
     * @param  array<int, int>  $setsWon
     * @return array{finished: bool, legsWonInSet: array<int, int>, setsWon: array<int, int>, currentSetNumber: int}
     */
    public static function applyLegWinToFfa(
        MatchFormat $format,
        int $winnerPlayerId,
        array $legsWonInSet,
        array $setsWon,
        int $currentSetNumber,
    ): array {
        $legsWonInSet[$winnerPlayerId] = (int) ($legsWonInSet[$winnerPlayerId] ?? 0) + 1;

        if ($format->isSingleSet()) {
            if (($legsWonInSet[$winnerPlayerId] ?? 0) >= $format->legsToWinSet) {
                $setsWon[$winnerPlayerId] = 1;

                return [
                    'finished' => true,
                    'legsWonInSet' => $legsWonInSet,
                    'setsWon' => $setsWon,
                    'currentSetNumber' => $currentSetNumber,
                ];
            }

            return [
                'finished' => false,
                'legsWonInSet' => $legsWonInSet,
                'setsWon' => $setsWon,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        if (($legsWonInSet[$winnerPlayerId] ?? 0) >= $format->legsToWinSet) {
            $setsWon[$winnerPlayerId] = (int) ($setsWon[$winnerPlayerId] ?? 0) + 1;
            foreach (array_keys($legsWonInSet) as $pid) {
                $legsWonInSet[(int) $pid] = 0;
            }
            $currentSetNumber++;

            foreach ($setsWon as $pid => $count) {
                if ($count >= $format->setsToWinMatch) {
                    return [
                        'finished' => true,
                        'legsWonInSet' => $legsWonInSet,
                        'setsWon' => $setsWon,
                        'currentSetNumber' => $currentSetNumber,
                    ];
                }
            }
        }

        return [
            'finished' => false,
            'legsWonInSet' => $legsWonInSet,
            'setsWon' => $setsWon,
            'currentSetNumber' => $currentSetNumber,
        ];
    }

    /**
     * @param  array<int, int>  $legsWonInSet
     * @param  array<int, int>  $setsWon
     * @return array{legsWonInSet: array<int, int>, setsWon: array<int, int>, currentSetNumber: int}
     */
    public static function revertLegWinOnFfa(
        MatchFormat $format,
        int $legWinnerId,
        array $legsWonInSet,
        array $setsWon,
        int $currentSetNumber,
    ): array {
        if ($format->isSingleSet()) {
            if (($legsWonInSet[$legWinnerId] ?? 0) > 0) {
                $legsWonInSet[$legWinnerId] = (int) $legsWonInSet[$legWinnerId] - 1;
            }

            return [
                'legsWonInSet' => $legsWonInSet,
                'setsWon' => $setsWon,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        $allLegsZero = true;
        foreach ($legsWonInSet as $count) {
            if ((int) $count > 0) {
                $allLegsZero = false;
                break;
            }
        }

        $setWasClosed = $allLegsZero && ((int) ($setsWon[$legWinnerId] ?? 0) > 0);

        if ($setWasClosed) {
            $setsWon[$legWinnerId] = (int) $setsWon[$legWinnerId] - 1;
            foreach (array_keys($legsWonInSet) as $pid) {
                $legsWonInSet[(int) $pid] = 0;
            }
            $legsWonInSet[$legWinnerId] = $format->legsToWinSet - 1;
            if ($currentSetNumber > 1) {
                $currentSetNumber--;
            }

            return [
                'legsWonInSet' => $legsWonInSet,
                'setsWon' => $setsWon,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        if (($legsWonInSet[$legWinnerId] ?? 0) > 0) {
            $legsWonInSet[$legWinnerId] = (int) $legsWonInSet[$legWinnerId] - 1;
        }

        return [
            'legsWonInSet' => $legsWonInSet,
            'setsWon' => $setsWon,
            'currentSetNumber' => $currentSetNumber,
        ];
    }

    /**
     * @param  array<int, int>  $legsWonInSet
     * @param  array<int, int>  $setsWon
     * @return array<int, int>
     */
    public static function legsWonForDisplay(MatchFormat $format, array $legsWonInSet, array $setsWon): array
    {
        if ($format->isSingleSet()) {
            return $legsWonInSet;
        }

        return $setsWon;
    }
}
