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

        $allowedAdvances = TournamentStartRules::allowedAdvancePerGroup($groupsCount);

        if (
            $allowedAdvances !== []
            && TournamentStartRules::isPowerOfTwo($advancePerGroup)
            && ! in_array($advancePerGroup, $allowedAdvances, true)
        ) {
            $errors['advancePerGroup'] = sprintf(
                'Dla %d grup dozwolony awans to: %s.',
                $groupsCount,
                implode(', ', $allowedAdvances),
            );
        }

        if ($groupsCount > 0 && $allowedAdvances === [] && ! isset($errors['groupsCount'])) {
            $errors['groupsCount'] = sprintf(
                'Dla %d grup nie da się ustawić awansu spełniającego limit %d graczy w drabince (MVP).',
                $groupsCount,
                TournamentStartRules::MAX_BRACKET_SIZE,
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
