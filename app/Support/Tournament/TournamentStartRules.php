<?php

namespace App\Support\Tournament;

final class TournamentStartRules
{
    public const MIN_PLAYERS = 4;

    /** Minimalna liczba zawodników w jednej grupie (round-robin ma sens od 3). */
    public const MIN_PLAYERS_PER_GROUP = 3;

    public const MIN_GROUPS = 2;

    /** MVP: maksymalna liczba awansujących do drabinki playoff (`grupy × awans`). */
    public const MAX_BRACKET_SIZE = 32;

    public const MIN_TABLETS = 1;

    public static function isPowerOfTwo(int $n): bool
    {
        return $n > 0 && ($n & ($n - 1)) === 0;
    }

    public static function bracketSize(int $groupsCount, int $advancePerGroup): int
    {
        return $groupsCount * $advancePerGroup;
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
     * Awans z grupy: reguły drabinki (potęgi 2, pełna drabinka ≤ MVP),
     * ograniczone rozmiarem największej grupy przy danym składzie.
     *
     * @return list<int>
     */
    public static function allowedAdvancePerGroupForPlayers(int $playerCount, int $groupsCount): array
    {
        $maxInGroup = self::maxPlayersInLargestGroup($playerCount, $groupsCount);

        if ($maxInGroup < 1) {
            return [];
        }

        return array_values(array_filter(
            self::allowedAdvancePerGroup($groupsCount),
            static fn (int $advance) => $advance <= $maxInGroup,
        ));
    }

    /**
     * Dozwolone wartości awansu z grupy dla danej liczby grup (MVP, pełna drabinka ≤ 32).
     * Bez składu — tylko reguły potęg 2; preferuj {@see allowedAdvancePerGroupForPlayers}.
     *
     * @return list<int>
     */
    public static function allowedAdvancePerGroup(int $groupsCount): array
    {
        if (! self::isPowerOfTwo($groupsCount) || $groupsCount < self::MIN_GROUPS) {
            return [];
        }

        $allowed = [];

        for ($advance = 1; $advance <= self::MAX_BRACKET_SIZE; $advance *= 2) {
            $bracketSize = self::bracketSize($groupsCount, $advance);

            if ($bracketSize > self::MAX_BRACKET_SIZE) {
                break;
            }

            if (self::isPowerOfTwo($bracketSize)) {
                $allowed[] = $advance;
            }
        }

        return $allowed;
    }

    /**
     * Maksymalna liczba grup przy danym składzie (co najmniej MIN_PLAYERS_PER_GROUP na grupę).
     */
    public static function maxGroupsForPlayerCount(int $playerCount): int
    {
        return intdiv(max(0, $playerCount), self::MIN_PLAYERS_PER_GROUP);
    }

    /**
     * Potęgi 2 od MIN_GROUPS w górę, które mieszczą się w składzie (min. MIN_PLAYERS_PER_GROUP na grupę).
     *
     * @return list<int>
     */
    public static function allowedGroupCountsForPlayers(int $playerCount, int $maxGroupsCap = 64): array
    {
        $maxGroups = min(self::maxGroupsForPlayerCount($playerCount), $maxGroupsCap);

        if ($maxGroups < self::MIN_GROUPS) {
            return [];
        }

        return array_values(array_filter(
            self::allowedGroupCounts($maxGroupsCap),
            static fn (int $groupsCount) => $groupsCount <= $maxGroups
                && $groupsCount <= $playerCount
                && intdiv($playerCount, $groupsCount) >= self::MIN_PLAYERS_PER_GROUP,
        ));
    }

    /**
     * @return list<int>
     */
    public static function allowedGroupCounts(int $maxGroups = 64): array
    {
        $options = [];

        for ($groups = self::MIN_GROUPS; $groups <= $maxGroups; $groups *= 2) {
            $options[] = $groups;
        }

        return $options;
    }

    /**
     * Mapa: liczba grup → dozwolone wartości awansu (tylko sensowne przy danym składzie).
     *
     * @return array<int, list<int>>
     */
    public static function advancesByGroupCountForPlayers(int $playerCount, int $maxGroupsCap = 64): array
    {
        $map = [];

        foreach (self::allowedGroupCountsForPlayers($playerCount, $maxGroupsCap) as $groupsCount) {
            $map[$groupsCount] = self::allowedAdvancePerGroupForPlayers($playerCount, $groupsCount);
        }

        return $map;
    }

    /**
     * Mapa: liczba grup → dozwolone wartości awansu (do kreatora startu).
     *
     * @return array<int, list<int>>
     */
    public static function advancesByGroupCount(int $maxGroups = 64): array
    {
        $map = [];

        foreach (self::allowedGroupCounts($maxGroups) as $groupsCount) {
            $map[$groupsCount] = self::allowedAdvancePerGroup($groupsCount);
        }

        return $map;
    }
}
