<?php

namespace App\Services\Tournament;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\BracketSide;
use App\Enums\TournamentFormat;
use App\Factories\PlayoffBracketFactory;
use App\Models\Tournament\Tournament;
use App\Support\Tournament\PlayoffSlotIds;

/**
 * Pełna drabinka do podglądu: istniejące mecze w swoich slotach, reszta pusta.
 */
class PlayoffBracketOutline
{
    public function __construct(private PlayoffBracketFactory $bracketFactory) {}

    /**
     * @param  array<string, list<PlayoffGameDomain>>  $existingByRound
     * @return array<string, list<PlayoffGameDomain>>
     */
    public function fill(Tournament $tournament, array $existingByRound, BracketSide $side): array
    {
        if ($tournament->format === TournamentFormat::DoubleElimination) {
            return $existingByRound;
        }

        if ($side === BracketSide::Consolation && ! $tournament->has_consolation_bracket) {
            return $existingByRound;
        }

        $size = $side === BracketSide::Consolation
            ? (int) ($tournament->consolation_bracket_size ?? 0)
            : (int) ($tournament->playoff_bracket_size ?? 0);

        if ($size < 2) {
            return $existingByRound;
        }

        $includeThird = $side !== BracketSide::Consolation
            || ((bool) $tournament->has_consolation_third_place && $size >= 4);
        $prefix = $side === BracketSide::Consolation ? PlayoffSlotIds::CONSOLATION_PREFIX : '';

        $bySlot = [];
        foreach ($existingByRound as $games) {
            foreach ($games as $game) {
                if ($game->slot !== '') {
                    $bySlot[$game->slot] = $game;
                }
            }
        }

        $filled = [];
        foreach ($this->bracketFactory->skeleton((int) $tournament->id, $size, $side, $prefix, $includeThird) as $slotGame) {
            $game = $bySlot[$slotGame->slot] ?? $slotGame;
            $filled[$game->round][] = $game;
            unset($bySlot[$slotGame->slot]);
        }

        foreach ($bySlot as $game) {
            $filled[$game->round][] = $game;
        }

        return $filled;
    }
}
