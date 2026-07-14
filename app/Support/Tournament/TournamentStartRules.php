<?php

namespace App\Support\Tournament;

final class TournamentStartRules
{
    public const MIN_PLAYERS = 4;

    /** Minimalna liczba zawodników w jednej grupie (round-robin ma sens od 3). */
    public const MIN_PLAYERS_PER_GROUP = 3;

    public const MIN_GROUPS = 2;

    /** MVP: maksymalna liczba awansujących do drabinki playoff. */
    public const MAX_BRACKET_SIZE = 32;

    public const MIN_BRACKET_SIZE = 4;

    public const MIN_TABLETS = 1;

    public static function isPowerOfTwo(int $n): bool
    {
        return $n > 0 && ($n & ($n - 1)) === 0;
    }

    /**
     * Największa grupa przy podziale zawodników (zgodnie z {@see TournamentGroupDistribution::groupSizes}).
     */
    public static function maxPlayersInLargestGroup(int $playerCount, int $groupsCount): int
    {
        if ($groupsCount < 1 || $playerCount < 0) {
            return 0;
        }

        $sizes = TournamentGroupDistribution::groupSizes($playerCount, $groupsCount);

        return $sizes === [] ? 0 : max($sizes);
    }

    /**
     * Maksymalna liczba grup przy danym składzie (co najmniej MIN_PLAYERS_PER_GROUP na grupę).
     */
    public static function maxGroupsForPlayerCount(int $playerCount): int
    {
        return intdiv(max(0, $playerCount), self::MIN_PLAYERS_PER_GROUP);
    }

    /**
     * Dowolna liczba grup od MIN_GROUPS w górę, która mieści się w składzie (min. MIN_PLAYERS_PER_GROUP na grupę).
     *
     * @return list<int>
     */
    public static function allowedGroupCountsForPlayers(int $playerCount, int $maxGroupsCap = 64): array
    {
        $maxGroups = min(self::maxGroupsForPlayerCount($playerCount), $maxGroupsCap);

        if ($maxGroups < self::MIN_GROUPS) {
            return [];
        }

        $options = [];

        for ($groups = self::MIN_GROUPS; $groups <= $maxGroups; $groups++) {
            if ($groups <= $playerCount && intdiv($playerCount, $groups) >= self::MIN_PLAYERS_PER_GROUP) {
                $options[] = $groups;
            }
        }

        return $options;
    }

    /**
     * @return list<int>
     */
    public static function allowedPlayoffBracketSizes(int $playerCount, int $groupsCount): array
    {
        if ($groupsCount < self::MIN_GROUPS || $playerCount < self::MIN_PLAYERS) {
            return [];
        }

        $groupSizes = TournamentGroupDistribution::groupSizes($playerCount, $groupsCount);
        $allowed = [];

        for ($bracketSize = self::MIN_BRACKET_SIZE; $bracketSize <= self::MAX_BRACKET_SIZE; $bracketSize *= 2) {
            if ($bracketSize < $groupsCount || $bracketSize > $playerCount) {
                continue;
            }

            try {
                TournamentGroupAdvanceDistribution::distribute($groupSizes, $bracketSize);
                $allowed[] = $bracketSize;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $allowed;
    }

    public static function bracketStageLabel(int $bracketSize): string
    {
        return match ($bracketSize) {
            32 => '1/16 finału',
            16 => '1/8 finału',
            8 => '1/4 finału',
            4 => '1/2 finału',
            default => sprintf('%d graczy', $bracketSize),
        };
    }

    public static function bracketOptionLabel(int $bracketSize): string
    {
        return sprintf(
            '%s — %d graczy awansujących',
            self::bracketStageLabel($bracketSize),
            $bracketSize,
        );
    }

    /**
     * Mapa: liczba grup → dozwolone rozmiary drabinki (potęgi 2).
     *
     * @return array<int, list<int>>
     */
    public static function bracketSizesByGroupCountForPlayers(int $playerCount, int $maxGroupsCap = 64): array
    {
        $map = [];

        foreach (self::allowedGroupCountsForPlayers($playerCount, $maxGroupsCap) as $groupsCount) {
            $map[$groupsCount] = self::allowedPlayoffBracketSizes($playerCount, $groupsCount);
        }

        return $map;
    }

    /**
     * Podgląd konfiguracji startu dla kreatora (grupy × drabinka → rozmiary grup i awans).
     *
     * @return array<int, array<int, array{groupSizes: list<int>, advances: list<int>}>>
     */
    public static function startConfigPreview(int $playerCount, int $maxGroupsCap = 64): array
    {
        $preview = [];

        foreach (self::allowedGroupCountsForPlayers($playerCount, $maxGroupsCap) as $groupsCount) {
            $groupSizes = TournamentGroupDistribution::groupSizes($playerCount, $groupsCount);
            $preview[$groupsCount] = [];

            foreach (self::allowedPlayoffBracketSizes($playerCount, $groupsCount) as $bracketSize) {
                $preview[$groupsCount][$bracketSize] = [
                    'groupSizes' => $groupSizes,
                    'advances' => TournamentGroupAdvanceDistribution::distribute($groupSizes, $bracketSize),
                ];
            }
        }

        return $preview;
    }

    /**
     * @return array<int, list<array{value: int, label: string}>>
     */
    public static function bracketOptionsByGroupCountForPlayers(int $playerCount, int $maxGroupsCap = 64): array
    {
        $map = [];

        foreach (self::bracketSizesByGroupCountForPlayers($playerCount, $maxGroupsCap) as $groupsCount => $sizes) {
            $map[$groupsCount] = array_map(
                static fn (int $bracketSize): array => [
                    'value' => $bracketSize,
                    'label' => self::bracketOptionLabel($bracketSize),
                ],
                $sizes,
            );
        }

        return $map;
    }
}
