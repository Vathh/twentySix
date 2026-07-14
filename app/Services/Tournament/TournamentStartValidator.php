<?php

namespace App\Services\Tournament;

use App\Support\Tournament\TournamentGroupAdvanceDistribution;
use App\Support\Tournament\TournamentGroupDistribution;
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
        int $playoffBracketSize,
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

        if ($groupsCount < TournamentStartRules::MIN_GROUPS) {
            $errors['groupsCount'] = sprintf(
                'Wymagane co najmniej %d grupy.',
                TournamentStartRules::MIN_GROUPS,
            );
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
                'Dozwolona liczba grup przy %d zawodnikach: od %d do %d.',
                $playerCount,
                $allowedGroupCounts[0],
                $allowedGroupCounts[array_key_last($allowedGroupCounts)],
            );
        }

        if (! TournamentStartRules::isPowerOfTwo($playoffBracketSize)) {
            $errors['playoffBracketSize'] = 'Rozmiar drabinki musi być potęgą 2 (4, 8, 16, 32).';
        }

        if ($playoffBracketSize > TournamentStartRules::MAX_BRACKET_SIZE) {
            $errors['playoffBracketSize'] = sprintf(
                'W MVP do drabinki awansuje maksymalnie %d graczy.',
                TournamentStartRules::MAX_BRACKET_SIZE,
            );
        }

        if ($playoffBracketSize < $groupsCount) {
            $errors['playoffBracketSize'] = sprintf(
                'Z każdej grupy musi awansować co najmniej jeden zawodnik (grup: %d, wybrana drabinka: %d).',
                $groupsCount,
                $playoffBracketSize,
            );
        }

        if ($playoffBracketSize > $playerCount) {
            $errors['playoffBracketSize'] = sprintf(
                'Drabinka (%d) nie może być większa niż liczba zawodników (%d).',
                $playoffBracketSize,
                $playerCount,
            );
        }

        $allowedBracketSizes = $playerCount >= TournamentStartRules::MIN_PLAYERS
            ? TournamentStartRules::allowedPlayoffBracketSizes($playerCount, $groupsCount)
            : [];

        if (
            $allowedBracketSizes !== []
            && TournamentStartRules::isPowerOfTwo($playoffBracketSize)
            && ! in_array($playoffBracketSize, $allowedBracketSizes, true)
        ) {
            $labels = array_map(
                static fn (int $size): string => TournamentStartRules::bracketOptionLabel($size),
                $allowedBracketSizes,
            );

            $errors['playoffBracketSize'] = sprintf(
                'Dla %d grup i %d zawodników dozwolone etapy drabinki: %s.',
                $groupsCount,
                $playerCount,
                implode('; ', $labels),
            );
        }

        if ($groupsCount > 0 && $allowedBracketSizes === [] && ! isset($errors['groupsCount'])) {
            $errors['groupsCount'] = sprintf(
                'Przy %d zawodnikach i %d grupach nie da się ustawić drabinki playoff.',
                $playerCount,
                $groupsCount,
            );
        }

        if (
            $playerCount >= TournamentStartRules::MIN_PLAYERS
            && $groupsCount >= TournamentStartRules::MIN_GROUPS
            && ! isset($errors['playoffBracketSize'])
            && TournamentStartRules::isPowerOfTwo($playoffBracketSize)
        ) {
            try {
                $groupSizes = TournamentGroupDistribution::groupSizes($playerCount, $groupsCount);
                TournamentGroupAdvanceDistribution::distribute($groupSizes, $playoffBracketSize);
            } catch (\InvalidArgumentException $e) {
                $errors['playoffBracketSize'] = 'Nie udało się rozłożyć miejsc awansujących między grupy.';
            }
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
