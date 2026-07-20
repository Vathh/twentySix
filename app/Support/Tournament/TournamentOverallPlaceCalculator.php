<?php

namespace App\Support\Tournament;

use App\Enums\GameStage;
use Illuminate\Support\Collection;

final class TournamentOverallPlaceCalculator
{
    /**
     * @param Collection<int, array{player_id: int, elimination_stage: GameStage, group_place: ?int, current_place: ?int}> $eliminated
     * @return array<int, int> player_id => miejsce w turnieju
     */
    public function calculate(int $playoffBracketSize, Collection $eliminated): array
    {
        $assignments = [];

        foreach ($eliminated as $row) {
            if (
                in_array($row['elimination_stage'], [GameStage::FINAL, GameStage::THIRD], true)
                && $row['current_place'] !== null
            ) {
                $assignments[$row['player_id']] = $row['current_place'];
            }
        }

        $playoffSharedPlaces = $this->playoffSharedPlaces($playoffBracketSize);

        foreach (GameStage::sharedPlacementStages($playoffBracketSize) as $stage) {
            $sharedPlace = $playoffSharedPlaces[$stage->value] ?? null;

            if ($sharedPlace === null) {
                continue;
            }

            foreach ($eliminated->filter(
                fn (array $row) => $row['elimination_stage'] === $stage,
            ) as $row) {
                $assignments[$row['player_id']] = $sharedPlace;
            }
        }

        $nextPlace = 5;

        foreach (GameStage::sharedPlacementStages($playoffBracketSize) as $stage) {
            $nextPlace += $this->losersCountAtStage($stage, $playoffBracketSize);
        }

        $groupEliminated = $eliminated
            ->filter(fn (array $row) => $row['elimination_stage'] === GameStage::GROUP)
            ->groupBy(fn (array $row) => $row['group_place'] ?? 0)
            ->sortKeys();

        foreach ($groupEliminated as $players) {
            foreach ($players as $row) {
                $assignments[$row['player_id']] = $nextPlace;
            }

            $nextPlace += $players->count();
        }

        return $assignments;
    }

    /**
     * @return array<string, int> stage value => miejsce ex aequo
     */
    private function playoffSharedPlaces(int $playoffBracketSize): array
    {
        $places = [];
        $nextPlace = 5;

        foreach (GameStage::sharedPlacementStages($playoffBracketSize) as $stage) {
            $places[$stage->value] = $nextPlace;
            $nextPlace += $this->losersCountAtStage($stage, $playoffBracketSize);
        }

        return $places;
    }

    private function losersCountAtStage(GameStage $stage, int $playoffBracketSize): int
    {
        return match ($stage) {
            GameStage::QUARTER => 4,
            GameStage::EIGHT => 8,
            GameStage::SIXTEEN => 16,
            default => 0,
        };
    }
}
