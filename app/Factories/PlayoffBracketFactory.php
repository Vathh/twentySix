<?php

namespace App\Factories;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\GameStage;
use App\Support\Tournament\PlayoffSlotIds;
use App\Support\Tournament\TournamentStartRules;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlayoffBracketFactory
{
    /**
     * @param  list<array{0: int|null, 1: int|null}>  $firstRoundPairs  null = bye
     * @return Collection<int, PlayoffGameDomain>
     */
    public function create(int $tournamentId, int $bracketSize, array $firstRoundPairs): Collection
    {
        if (! TournamentStartRules::isPowerOfTwo($bracketSize)
            || $bracketSize < 2
            || $bracketSize > TournamentStartRules::MAX_BRACKET_SIZE
        ) {
            throw new InvalidArgumentException(sprintf(
                'Rozmiar drabinki musi być potęgą 2 z przedziału 2–%d (otrzymano %d).',
                TournamentStartRules::MAX_BRACKET_SIZE,
                $bracketSize,
            ));
        }

        $expectedPairs = intdiv($bracketSize, 2);

        if (count($firstRoundPairs) !== $expectedPairs) {
            throw new InvalidArgumentException(sprintf(
                'Oczekiwano %d par w pierwszej rundzie, otrzymano %d.',
                $expectedPairs,
                count($firstRoundPairs),
            ));
        }

        $games = $this->buildTree($tournamentId, $bracketSize);

        return $this->assignFirstRoundPlayers($games, $firstRoundPairs, $bracketSize);
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildTree(int $tournamentId, int $bracketSize): Collection
    {
        $games = collect();
        $matchCount = intdiv($bracketSize, 2);

        while ($matchCount >= 1) {
            $stage = PlayoffSlotIds::stageForFirstRoundMatchCount($matchCount);

            if ($stage === GameStage::FINAL) {
                $games->push(PlayoffGameDomain::createForBracket(
                    tournamentId: $tournamentId,
                    round: GameStage::FINAL,
                    slot: PlayoffSlotIds::FINAL,
                ));

                if ($bracketSize >= 4) {
                    $games->push(PlayoffGameDomain::createForBracket(
                        tournamentId: $tournamentId,
                        round: GameStage::THIRD,
                        slot: PlayoffSlotIds::THIRD,
                    ));
                }

                break;
            }

            $nextMatchCount = intdiv($matchCount, 2);
            $nextStage = PlayoffSlotIds::stageForFirstRoundMatchCount($nextMatchCount);

            for ($index = 1; $index <= $matchCount; $index++) {
                $nextIndex = intdiv($index - 1, 2) + 1;
                $playerSlot = $index % 2 === 1 ? 'A' : 'B';
                $targetSlot = PlayoffSlotIds::forStage($nextStage, $nextIndex);

                $games->push(PlayoffGameDomain::createForBracket(
                    tournamentId: $tournamentId,
                    round: $stage,
                    slot: PlayoffSlotIds::forStage($stage, $index),
                    winnerDestinationSlot: PlayoffSlotIds::destination($targetSlot, $playerSlot),
                ));
            }

            $matchCount = $nextMatchCount;
        }

        return $games;
    }

    /**
     * @param  Collection<int, PlayoffGameDomain>  $games
     * @param  list<array{0: int|null, 1: int|null}>  $firstRoundPairs
     * @return Collection<int, PlayoffGameDomain>
     */
    private function assignFirstRoundPlayers(
        Collection $games,
        array $firstRoundPairs,
        int $bracketSize,
    ): Collection {
        $firstRound = PlayoffSlotIds::stageForFirstRoundMatchCount(intdiv($bracketSize, 2));
        $pairQueue = collect($firstRoundPairs);

        return $games->map(function (PlayoffGameDomain $game) use ($pairQueue, $firstRound) {
            if ($game->round !== $firstRound->value) {
                return $game;
            }

            $pair = $pairQueue->shift();

            return $game->withPlayerIds(
                player1Id: $pair[0],
                player2Id: $pair[1],
            );
        });
    }
}
