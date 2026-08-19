<?php

namespace App\Domain\GameScoring;

final class MatchFormatScoring
{
    /**
     * Czysta funkcja: liczy nowy stan meczu H2H po wygranym legu. Nie mutuje Eloquent —
     * zwraca nowy stan, który Service ma zapisać przez Repository.
     *
     * @return array{finished: bool, winnerId: ?int, player1Score: int, player2Score: int, player1LegsInSet: int, player2LegsInSet: int, currentSetNumber: int}
     */
    public static function applyLegWinToH2hGame(
        MatchFormat $format,
        int $winnerPlayerId,
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        int $player1LegsInSet,
        int $player2LegsInSet,
        int $currentSetNumber,
    ): array {
        if ($format->isSingleSet()) {
            if ($winnerPlayerId === $player1Id) {
                $player1Score++;
            } else {
                $player2Score++;
            }

            $winAt = $format->legsToWinSet;
            $reachedWin = $player1Score >= $winAt || $player2Score >= $winAt;
            $playedAll = $format->isBestOf() && ($player1Score + $player2Score) >= $format->winLength;
            $finished = $reachedWin || $playedAll;
            $winnerId = null;
            if ($finished) {
                if ($player1Score === $player2Score) {
                    $winnerId = null;
                } else {
                    $winnerId = $player1Score > $player2Score ? $player1Id : $player2Id;
                }
            }

            return [
                'finished' => $finished,
                'winnerId' => $winnerId,
                'player1Score' => $player1Score,
                'player2Score' => $player2Score,
                'player1LegsInSet' => $player1LegsInSet,
                'player2LegsInSet' => $player2LegsInSet,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        if ($winnerPlayerId === $player1Id) {
            $player1LegsInSet++;
        } else {
            $player2LegsInSet++;
        }

        if ($player1LegsInSet < $format->legsToWinSet && $player2LegsInSet < $format->legsToWinSet) {
            return [
                'finished' => false,
                'winnerId' => null,
                'player1Score' => $player1Score,
                'player2Score' => $player2Score,
                'player1LegsInSet' => $player1LegsInSet,
                'player2LegsInSet' => $player2LegsInSet,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        if ($player1LegsInSet >= $format->legsToWinSet) {
            $player1Score++;
        } else {
            $player2Score++;
        }

        $player1LegsInSet = 0;
        $player2LegsInSet = 0;
        $currentSetNumber++;

        $finished = $player1Score >= $format->setsToWinMatch || $player2Score >= $format->setsToWinMatch;

        return [
            'finished' => $finished,
            'winnerId' => $finished ? ($player1Score >= $format->setsToWinMatch ? $player1Id : $player2Id) : null,
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
            'player1LegsInSet' => $player1LegsInSet,
            'player2LegsInSet' => $player2LegsInSet,
            'currentSetNumber' => $currentSetNumber,
        ];
    }

    /**
     * Czysta funkcja: liczy stan meczu H2H po cofnięciu wygranego lega. Nie mutuje Eloquent —
     * zwraca nowy stan, który Service ma zapisać przez Repository.
     *
     * @return array{player1Score: int, player2Score: int, player1LegsInSet: int, player2LegsInSet: int, currentSetNumber: int}
     */
    public static function revertLegWinOnH2hGame(
        MatchFormat $format,
        ?int $legWinnerId,
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        int $player1LegsInSet,
        int $player2LegsInSet,
        int $currentSetNumber,
    ): array {
        $unchanged = [
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
            'player1LegsInSet' => $player1LegsInSet,
            'player2LegsInSet' => $player2LegsInSet,
            'currentSetNumber' => $currentSetNumber,
        ];

        if ($legWinnerId === null) {
            return $unchanged;
        }

        if ($format->isSingleSet()) {
            if ($player1Id === $legWinnerId && $player1Score > 0) {
                $player1Score--;
            } elseif ($player2Id === $legWinnerId && $player2Score > 0) {
                $player2Score--;
            }

            return [
                'player1Score' => $player1Score,
                'player2Score' => $player2Score,
                'player1LegsInSet' => $player1LegsInSet,
                'player2LegsInSet' => $player2LegsInSet,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        $setWasClosed = $player1LegsInSet === 0 && $player2LegsInSet === 0
            && ($player1Score > 0 || $player2Score > 0);

        if ($setWasClosed) {
            if ($player1Id === $legWinnerId && $player1Score > 0) {
                $player1Score--;
                $player1LegsInSet = $format->legsToWinSet - 1;
            } elseif ($player2Id === $legWinnerId && $player2Score > 0) {
                $player2Score--;
                $player2LegsInSet = $format->legsToWinSet - 1;
            }

            if ($currentSetNumber > 1) {
                $currentSetNumber--;
            }

            return [
                'player1Score' => $player1Score,
                'player2Score' => $player2Score,
                'player1LegsInSet' => $player1LegsInSet,
                'player2LegsInSet' => $player2LegsInSet,
                'currentSetNumber' => $currentSetNumber,
            ];
        }

        if ($player1Id === $legWinnerId && $player1LegsInSet > 0) {
            $player1LegsInSet--;
        } elseif ($player2Id === $legWinnerId && $player2LegsInSet > 0) {
            $player2LegsInSet--;
        }

        return [
            'player1Score' => $player1Score,
            'player2Score' => $player2Score,
            'player1LegsInSet' => $player1LegsInSet,
            'player2LegsInSet' => $player2LegsInSet,
            'currentSetNumber' => $currentSetNumber,
        ];
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
