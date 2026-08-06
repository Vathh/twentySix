<?php

namespace App\Factories;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\BracketSide;
use App\Support\Tournament\PlayoffSlotIds;
use App\Support\Tournament\TournamentStartRules;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Double elimination: winners + losers + grand final (+ optional GF2).
 *
 * Sloty: W{r}-{i}, L{r}-{i}, GF1, GF2.
 */
class DoubleEliminationBracketFactory
{
    /**
     * @param  list<array{0: int|null, 1: int|null}>  $firstRoundPairs
     * @return Collection<int, PlayoffGameDomain>
     */
    public function create(
        int $tournamentId,
        int $bracketSize,
        array $firstRoundPairs,
        bool $resetGrandFinal,
    ): Collection {
        if (! TournamentStartRules::isPowerOfTwo($bracketSize)
            || $bracketSize < 4
            || $bracketSize > TournamentStartRules::MAX_BRACKET_SIZE
        ) {
            throw new InvalidArgumentException(sprintf(
                'DE: rozmiar drabinki musi być potęgą 2 z przedziału 4–%d (otrzymano %d).',
                TournamentStartRules::MAX_BRACKET_SIZE,
                $bracketSize,
            ));
        }

        $expectedPairs = intdiv($bracketSize, 2);
        if (count($firstRoundPairs) !== $expectedPairs) {
            throw new InvalidArgumentException(sprintf(
                'Oczekiwano %d par w pierwszej rundzie WB, otrzymano %d.',
                $expectedPairs,
                count($firstRoundPairs),
            ));
        }

        $wbRounds = (int) log($bracketSize, 2);
        $games = collect();

        // --- Winners bracket ---
        for ($r = 0; $r < $wbRounds; $r++) {
            $matchCount = intdiv($bracketSize, 2 ** ($r + 1));
            for ($i = 1; $i <= $matchCount; $i++) {
                $slot = "W{$r}-{$i}";
                $winnerDest = null;
                $loserDest = null;

                if ($r < $wbRounds - 1) {
                    $nextIndex = intdiv($i - 1, 2) + 1;
                    $ab = $i % 2 === 1 ? 'A' : 'B';
                    $winnerDest = PlayoffSlotIds::destination("W".($r + 1)."-{$nextIndex}", $ab);
                } else {
                    $winnerDest = PlayoffSlotIds::destination('GF1', 'A');
                }

                $loserDest = $this->wbLoserDestination($r, $i, $wbRounds, $bracketSize);

                $games->push(PlayoffGameDomain::createForBracket(
                    tournamentId: $tournamentId,
                    round: "W{$r}",
                    slot: $slot,
                    winnerDestinationSlot: $winnerDest,
                    bracketSide: BracketSide::Winners,
                    loserDestinationSlot: $loserDest,
                ));
            }
        }

        // --- Losers bracket ---
        $lbRoundCount = 2 * $wbRounds - 2;
        for ($r = 0; $r < $lbRoundCount; $r++) {
            $matchCount = $this->lbMatchCount($r, $bracketSize, $wbRounds);
            for ($i = 1; $i <= $matchCount; $i++) {
                $slot = "L{$r}-{$i}";
                $winnerDest = $this->lbWinnerDestination($r, $i, $lbRoundCount);

                $games->push(PlayoffGameDomain::createForBracket(
                    tournamentId: $tournamentId,
                    round: "L{$r}",
                    slot: $slot,
                    winnerDestinationSlot: $winnerDest,
                    bracketSide: BracketSide::Losers,
                    loserDestinationSlot: null,
                ));
            }
        }

        // --- Grand Final ---
        // Destynacje GF1 przy resecie obsługuje PlayoffService (tylko gdy wygra strona LB).
        $games->push(PlayoffGameDomain::createForBracket(
            tournamentId: $tournamentId,
            round: 'GF',
            slot: 'GF1',
            winnerDestinationSlot: null,
            bracketSide: BracketSide::GrandFinal,
            loserDestinationSlot: null,
        ));

        if ($resetGrandFinal) {
            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: 'GF2',
                slot: 'GF2',
                winnerDestinationSlot: null,
                bracketSide: BracketSide::GrandFinal,
                loserDestinationSlot: null,
            ));
        }

        return $this->assignFirstRoundPlayers($games, $firstRoundPairs);
    }

    /**
     * Drop z WB round r, match i → slot LB.
     */
    private function wbLoserDestination(int $wbRound, int $matchIndex, int $wbRounds, int $bracketSize): string
    {
        if ($wbRound === 0) {
            $lbMatch = intdiv($matchIndex - 1, 2) + 1;
            $ab = $matchIndex % 2 === 1 ? 'A' : 'B';

            return PlayoffSlotIds::destination("L0-{$lbMatch}", $ab);
        }

        // WB round r>0 drops into LB round (2*r - 1) — "major" round that mixes droppers
        $lbRound = 2 * $wbRound - 1;
        $lbMatchCount = $this->lbMatchCount($lbRound, $bracketSize, $wbRounds);

        // Cross to reduce rematches: reverse order of droppers into B slots
        $lbMatch = $lbMatchCount - $matchIndex + 1;
        if ($lbMatch < 1) {
            $lbMatch = 1;
        }

        return PlayoffSlotIds::destination("L{$lbRound}-{$lbMatch}", 'B');
    }

    private function lbMatchCount(int $lbRound, int $bracketSize, int $wbRounds): int
    {
        // L0=N/4; pary rund (0–1), (2–3), … mają tę samą liczbę meczów i połowią się co 2 rundy.
        $effective = $lbRound - ($lbRound % 2);

        return max(1, intdiv($bracketSize, 2 ** (intdiv($effective, 2) + 2)));
    }

    private function lbWinnerDestination(int $lbRound, int $matchIndex, int $lbRoundCount): string
    {
        if ($lbRound === $lbRoundCount - 1) {
            return PlayoffSlotIds::destination('GF1', 'B');
        }

        $nextRound = $lbRound + 1;
        // Even→odd (0→1, 2→3): same match index, slot A (dropper takes B)
        // Odd→even (1→2, 3→4): winners play each other → ceil(i/2)
        if ($lbRound % 2 === 0) {
            return PlayoffSlotIds::destination("L{$nextRound}-{$matchIndex}", 'A');
        }

        $nextIndex = intdiv($matchIndex - 1, 2) + 1;
        $ab = $matchIndex % 2 === 1 ? 'A' : 'B';

        return PlayoffSlotIds::destination("L{$nextRound}-{$nextIndex}", $ab);
    }

    /**
     * @param  Collection<int, PlayoffGameDomain>  $games
     * @param  list<array{0: int|null, 1: int|null}>  $firstRoundPairs
     * @return Collection<int, PlayoffGameDomain>
     */
    private function assignFirstRoundPlayers(Collection $games, array $firstRoundPairs): Collection
    {
        $pairQueue = collect($firstRoundPairs);

        return $games->map(function (PlayoffGameDomain $game) use ($pairQueue) {
            if ($game->round !== 'W0') {
                return $game;
            }

            $pair = $pairQueue->shift();

            return $game->withPlayerIds($pair[0], $pair[1]);
        });
    }
}
