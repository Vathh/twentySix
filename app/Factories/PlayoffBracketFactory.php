<?php /** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\GameStage;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
use App\Support\Tournament\TournamentStartRules;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlayoffBracketFactory
{
    /**
     * @param list<array{0: int, 1: int}> $firstRoundPairs
     * @return Collection<int, PlayoffGameDomain>
     */
    public function create(int $tournamentId, int $bracketSize, array $firstRoundPairs): Collection
    {
        if (! TournamentStartRules::isPowerOfTwo($bracketSize) || $bracketSize < 2 || $bracketSize > 32) {
            throw new InvalidArgumentException(sprintf(
                'Rozmiar drabinki musi być potęgą 2 z przedziału 2–32 (otrzymano %d).',
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

        $games = match ($bracketSize) {
            2 => $this->buildFor2($tournamentId),
            4 => $this->buildFor4($tournamentId),
            8 => $this->buildFor8($tournamentId),
            16 => $this->buildFor16($tournamentId),
            32 => $this->buildFor32($tournamentId),
            default => throw new InvalidArgumentException("Nieobsługiwany rozmiar drabinki: {$bracketSize}."),
        };

        return $this->assignFirstRoundPlayers($games, $firstRoundPairs, $bracketSize);
    }

    /**
     * @param list<int> $playerIds
     * @return Collection<int, PlayoffGameDomain>
     *
     * @deprecated Użyj {@see create()} z parami pierwszej rundy.
     */
    public function createFor16(int $tournamentId, array $playerIds): Collection
    {
        $advancing = array_map(
            fn (int $playerId, int $index) => [
                'player_id' => $playerId,
                'group_number' => $index + 1,
            ],
            $playerIds,
            array_keys($playerIds),
        );

        $pairs = \App\Support\Tournament\PlayoffFirstRoundPairing::pair($advancing);

        return $this->create($tournamentId, 16, $pairs);
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildFor2(int $tournamentId): Collection
    {
        return collect([
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::FINAL,
                slot: PlayoffSlot::FINAL,
            ),
        ]);
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildFor4(int $tournamentId): Collection
    {
        return collect([
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::SEMI,
                slot: PlayoffSlot::SEMI_1,
                winnerDestinationSlot: WinnerDestinationSlot::FINAL_A,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::SEMI,
                slot: PlayoffSlot::SEMI_2,
                winnerDestinationSlot: WinnerDestinationSlot::FINAL_B,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::FINAL,
                slot: PlayoffSlot::FINAL,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::THIRD,
                slot: PlayoffSlot::THIRD,
            ),
        ]);
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildFor8(int $tournamentId): Collection
    {
        $games = collect();

        foreach (range(1, 4) as $index) {
            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::QUARTER,
                slot: PlayoffSlot::from('QF_'.$index),
                winnerDestinationSlot: WinnerDestinationSlot::from(
                    $index <= 2 ? 'SEMI_1-'.($index === 1 ? 'A' : 'B') : 'SEMI_2-'.($index === 3 ? 'A' : 'B'),
                ),
            ));
        }

        return $games->merge($this->appendSemiFinalAndPlaces($tournamentId));
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildFor16(int $tournamentId): Collection
    {
        $games = collect();

        foreach (range(1, 8) as $index) {
            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::EIGHT,
                slot: PlayoffSlot::from('EIGHT_'.$index),
                winnerDestinationSlot: WinnerDestinationSlot::from(
                    'QF_'.intdiv($index - 1, 2) + 1 .'-'.($index % 2 === 1 ? 'A' : 'B'),
                ),
            ));
        }

        return $games->merge($this->appendQuarterFinalAndPlaces($tournamentId));
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function buildFor32(int $tournamentId): Collection
    {
        $games = collect();

        foreach (range(1, 16) as $index) {
            $eightIndex = intdiv($index - 1, 2) + 1;

            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::SIXTEEN,
                slot: PlayoffSlot::from('SIXTEEN_'.$index),
                winnerDestinationSlot: WinnerDestinationSlot::from(
                    'EIGHT_'.$eightIndex.'-'.($index % 2 === 1 ? 'A' : 'B'),
                ),
            ));
        }

        foreach (range(1, 8) as $index) {
            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::EIGHT,
                slot: PlayoffSlot::from('EIGHT_'.$index),
                winnerDestinationSlot: WinnerDestinationSlot::from(
                    'QF_'.intdiv($index - 1, 2) + 1 .'-'.($index % 2 === 1 ? 'A' : 'B'),
                ),
            ));
        }

        return $games->merge($this->appendQuarterFinalAndPlaces($tournamentId));
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function appendQuarterFinalAndPlaces(int $tournamentId): Collection
    {
        $games = collect();

        foreach (range(1, 4) as $index) {
            $games->push(PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::QUARTER,
                slot: PlayoffSlot::from('QF_'.$index),
                winnerDestinationSlot: WinnerDestinationSlot::from(
                    $index <= 2 ? 'SEMI_1-'.($index === 1 ? 'A' : 'B') : 'SEMI_2-'.($index === 3 ? 'A' : 'B'),
                ),
            ));
        }

        return $games->merge($this->appendSemiFinalAndPlaces($tournamentId));
    }

    /**
     * @return Collection<int, PlayoffGameDomain>
     */
    private function appendSemiFinalAndPlaces(int $tournamentId): Collection
    {
        return collect([
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::SEMI,
                slot: PlayoffSlot::SEMI_1,
                winnerDestinationSlot: WinnerDestinationSlot::FINAL_A,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::SEMI,
                slot: PlayoffSlot::SEMI_2,
                winnerDestinationSlot: WinnerDestinationSlot::FINAL_B,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::FINAL,
                slot: PlayoffSlot::FINAL,
            ),
            PlayoffGameDomain::createForBracket(
                tournamentId: $tournamentId,
                round: GameStage::THIRD,
                slot: PlayoffSlot::THIRD,
            ),
        ]);
    }

    /**
     * @param Collection<int, PlayoffGameDomain> $games
     * @param list<array{0: int, 1: int}> $firstRoundPairs
     * @return Collection<int, PlayoffGameDomain>
     */
    private function assignFirstRoundPlayers(Collection $games, array $firstRoundPairs, int $bracketSize): Collection
    {
        $firstRound = match ($bracketSize) {
            2 => GameStage::FINAL,
            4 => GameStage::SEMI,
            8 => GameStage::QUARTER,
            16 => GameStage::EIGHT,
            32 => GameStage::SIXTEEN,
            default => null,
        };

        $pairQueue = collect($firstRoundPairs);

        return $games->map(function (PlayoffGameDomain $game) use ($pairQueue, $firstRound) {
            if ($game->round === $firstRound) {
                $pair = $pairQueue->shift();

                return $game->withPlayerIds(
                    player1Id: $pair[0],
                    player2Id: $pair[1],
                );
            }

            return $game;
        });
    }
}
