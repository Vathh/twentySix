<?php

namespace App\Domain\League;

/**
 * Mini-drabinka SE do rozstrzygania remisów (2–4 graczy).
 * Seeding = aktualna kolejność w grupie remisowej.
 */
final class LeagueTieBreakBracket
{
    /**
     * @param  list<int>  $seededPlayerIds  kolejność (najlepszy pierwszy)
     * @param  list<array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}>  $completedGames
     * @return array{ordered: list<int>|null, pending: list<array{player1Id: int, player2Id: int, bracketRound: int, isThirdPlace: bool}>}
     */
    public static function next(array $seededPlayerIds, array $completedGames): array
    {
        $seeds = array_values($seededPlayerIds);
        $n = count($seeds);

        if ($n < 2) {
            return ['ordered' => $seeds, 'pending' => []];
        }

        $finished = array_values(array_filter(
            $completedGames,
            static fn (array $g) => ($g['status'] ?? '') === 'finished' && ($g['winnerId'] ?? null) !== null,
        ));

        if ($n === 2) {
            return self::twoPlayer($seeds, $finished);
        }
        if ($n === 3) {
            return self::threePlayer($seeds, $finished);
        }

        return self::fourPlayer($seeds, $finished);
    }

    /**
     * @param  list<int>  $seeds
     * @param  list<array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}>  $finished
     * @return array{ordered: list<int>|null, pending: list<array{player1Id: int, player2Id: int, bracketRound: int, isThirdPlace: bool}>}
     */
    private static function twoPlayer(array $seeds, array $finished): array
    {
        $game = self::findGame($finished, $seeds[0], $seeds[1]);
        if ($game === null) {
            return [
                'ordered' => null,
                'pending' => [[
                    'player1Id' => $seeds[0],
                    'player2Id' => $seeds[1],
                    'bracketRound' => 1,
                    'isThirdPlace' => false,
                ]],
            ];
        }

        $winner = (int) $game['winnerId'];
        $loser = $winner === $seeds[0] ? $seeds[1] : $seeds[0];

        return ['ordered' => [$winner, $loser], 'pending' => []];
    }

    /**
     * @param  list<int>  $seeds
     * @param  list<array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}>  $finished
     * @return array{ordered: list<int>|null, pending: list<array{player1Id: int, player2Id: int, bracketRound: int, isThirdPlace: bool}>}
     */
    private static function threePlayer(array $seeds, array $finished): array
    {
        $semi = self::findGame($finished, $seeds[1], $seeds[2], 1);
        if ($semi === null) {
            return [
                'ordered' => null,
                'pending' => [[
                    'player1Id' => $seeds[1],
                    'player2Id' => $seeds[2],
                    'bracketRound' => 1,
                    'isThirdPlace' => false,
                ]],
            ];
        }

        $semiWinner = (int) $semi['winnerId'];
        $semiLoser = $semiWinner === $seeds[1] ? $seeds[2] : $seeds[1];
        $final = self::findGame($finished, $seeds[0], $semiWinner, 2);
        if ($final === null) {
            return [
                'ordered' => null,
                'pending' => [[
                    'player1Id' => $seeds[0],
                    'player2Id' => $semiWinner,
                    'bracketRound' => 2,
                    'isThirdPlace' => false,
                ]],
            ];
        }

        $winner = (int) $final['winnerId'];
        $second = $winner === $seeds[0] ? $semiWinner : $seeds[0];

        return ['ordered' => [$winner, $second, $semiLoser], 'pending' => []];
    }

    /**
     * @param  list<int>  $seeds
     * @param  list<array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}>  $finished
     * @return array{ordered: list<int>|null, pending: list<array{player1Id: int, player2Id: int, bracketRound: int, isThirdPlace: bool}>}
     */
    private static function fourPlayer(array $seeds, array $finished): array
    {
        $semiA = self::findGame($finished, $seeds[0], $seeds[3], 1);
        $semiB = self::findGame($finished, $seeds[1], $seeds[2], 1);
        $pending = [];
        if ($semiA === null) {
            $pending[] = [
                'player1Id' => $seeds[0],
                'player2Id' => $seeds[3],
                'bracketRound' => 1,
                'isThirdPlace' => false,
            ];
        }
        if ($semiB === null) {
            $pending[] = [
                'player1Id' => $seeds[1],
                'player2Id' => $seeds[2],
                'bracketRound' => 1,
                'isThirdPlace' => false,
            ];
        }
        if ($pending !== []) {
            return ['ordered' => null, 'pending' => $pending];
        }

        $winnerA = (int) $semiA['winnerId'];
        $loserA = $winnerA === $seeds[0] ? $seeds[3] : $seeds[0];
        $winnerB = (int) $semiB['winnerId'];
        $loserB = $winnerB === $seeds[1] ? $seeds[2] : $seeds[1];

        $final = self::findGame($finished, $winnerA, $winnerB, 2);
        $third = self::findGame($finished, $loserA, $loserB, 2, true);
        $pending = [];
        if ($final === null) {
            $pending[] = [
                'player1Id' => $winnerA,
                'player2Id' => $winnerB,
                'bracketRound' => 2,
                'isThirdPlace' => false,
            ];
        }
        if ($third === null) {
            $pending[] = [
                'player1Id' => $loserA,
                'player2Id' => $loserB,
                'bracketRound' => 2,
                'isThirdPlace' => true,
            ];
        }
        if ($pending !== []) {
            return ['ordered' => null, 'pending' => $pending];
        }

        $first = (int) $final['winnerId'];
        $second = $first === $winnerA ? $winnerB : $winnerA;
        $thirdPlace = (int) $third['winnerId'];
        $fourth = $thirdPlace === $loserA ? $loserB : $loserA;

        return ['ordered' => [$first, $second, $thirdPlace, $fourth], 'pending' => []];
    }

    /**
     * @param  list<array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}>  $games
     * @return array{player1Id: int, player2Id: int, winnerId: ?int, status: string, bracketRound: int, isThirdPlace: bool}|null
     */
    private static function findGame(array $games, int $a, int $b, ?int $round = null, bool $thirdPlace = false): ?array
    {
        foreach ($games as $game) {
            $pair = [(int) $game['player1Id'], (int) $game['player2Id']];
            if (! in_array($a, $pair, true) || ! in_array($b, $pair, true)) {
                continue;
            }
            if ($round !== null && (int) $game['bracketRound'] !== $round) {
                continue;
            }
            if ((bool) $game['isThirdPlace'] !== $thirdPlace) {
                continue;
            }

            return $game;
        }

        return null;
    }
}
