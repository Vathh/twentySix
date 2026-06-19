<?php

namespace App\Services\Tournament;

use App\Support\Tournament\TournamentStartRules;
use Illuminate\Validation\ValidationException;

class TournamentStartValidator
{
    /**
     * @throws ValidationException
     */
    public function validate(
        int $playerCount,
        int $groupsCount,
        int $advancePerGroup,
        int $tabletsCount,
    ): void {
        $errors = [];

        if ($playerCount < TournamentStartRules::MIN_PLAYERS) {
            $errors['selectedPlayers'] = sprintf(
                'Turniej wymaga minimum %d zawodników (wybrano %d).',
                TournamentStartRules::MIN_PLAYERS,
                $playerCount,
            );
        }

        if (! TournamentStartRules::isPowerOfTwo($groupsCount) || $groupsCount < TournamentStartRules::MIN_GROUPS) {
            $errors['groupsCount'] = 'Liczba grup musi być potęgą 2 (np. 2, 4, 8, 16, 32, 64).';
        }

        if ($playerCount >= TournamentStartRules::MIN_PLAYERS && $groupsCount > $playerCount) {
            $errors['groupsCount'] = 'Liczba grup nie może przekraczać liczby zawodników.';
        }

        if (
            $groupsCount > 0
            && $playerCount >= TournamentStartRules::MIN_PLAYERS
            && intdiv($playerCount, $groupsCount) < TournamentStartRules::MIN_PLAYERS_PER_GROUP
        ) {
            $errors['groupsCount'] = sprintf(
                'Każda grupa musi mieć co najmniej %d zawodników (przy %d graczach i %d grupach to %d na grupę).',
                TournamentStartRules::MIN_PLAYERS_PER_GROUP,
                $playerCount,
                $groupsCount,
                intdiv($playerCount, $groupsCount),
            );
        }

        $allowedGroupCounts = TournamentStartRules::allowedGroupCountsForPlayers($playerCount);

        if (
            $playerCount >= TournamentStartRules::MIN_PLAYERS
            && $allowedGroupCounts !== []
            && ! in_array($groupsCount, $allowedGroupCounts, true)
        ) {
            $errors['groupsCount'] = sprintf(
                'Dozwolona liczba grup przy %d zawodnikach: %s.',
                $playerCount,
                implode(', ', $allowedGroupCounts),
            );
        }

        if (! TournamentStartRules::isPowerOfTwo($advancePerGroup)) {
            $errors['advancePerGroup'] = 'Awans z grupy musi być potęgą 2 (1, 2, 4, …).';
        }

        $bracketSize = TournamentStartRules::bracketSize($groupsCount, $advancePerGroup);

        if (
            TournamentStartRules::isPowerOfTwo($groupsCount)
            && TournamentStartRules::isPowerOfTwo($advancePerGroup)
            && ! TournamentStartRules::isPowerOfTwo($bracketSize)
        ) {
            $errors['advancePerGroup'] = 'Iloczyn liczby grup i awansu musi dać potęgę 2 (pełna drabinka bez wolnych losów).';
        }

        if ($bracketSize > TournamentStartRules::MAX_BRACKET_SIZE) {
            $errors['advancePerGroup'] = sprintf(
                'W MVP do drabinki awansuje maksymalnie %d graczy (grupy × awans = %d).',
                TournamentStartRules::MAX_BRACKET_SIZE,
                $bracketSize,
            );
        }

        $allowedAdvances = $playerCount >= TournamentStartRules::MIN_PLAYERS
            ? TournamentStartRules::allowedAdvancePerGroupForPlayers($playerCount, $groupsCount)
            : TournamentStartRules::allowedAdvancePerGroup($groupsCount);

        if (
            $allowedAdvances !== []
            && TournamentStartRules::isPowerOfTwo($advancePerGroup)
            && ! in_array($advancePerGroup, $allowedAdvances, true)
        ) {
            $maxInGroup = TournamentStartRules::maxPlayersInLargestGroup($playerCount, $groupsCount);

            $errors['advancePerGroup'] = sprintf(
                'Dla %d grup i %d zawodników dozwolony awans to: %s (największa grupa: %d).',
                $groupsCount,
                $playerCount,
                implode(', ', $allowedAdvances),
                $maxInGroup,
            );
        }

        if ($groupsCount > 0 && $allowedAdvances === [] && ! isset($errors['groupsCount'])) {
            $errors['groupsCount'] = sprintf(
                'Przy %d zawodnikach i %d grupach nie da się ustawić awansu (drabinka MVP lub rozmiar grup).',
                $playerCount,
                $groupsCount,
            );
        }

        if ($tabletsCount < TournamentStartRules::MIN_TABLETS) {
            $errors['tabletsCount'] = sprintf(
                'Wymagana co najmniej %d tablet.',
                TournamentStartRules::MIN_TABLETS,
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
