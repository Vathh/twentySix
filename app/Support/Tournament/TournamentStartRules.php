<?php

namespace App\Support\Tournament;

final class TournamentStartRules
{
    public const MIN_PLAYERS = 4;

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
     * Dozwolone wartości awansu z grupy dla danej liczby grup (MVP, pełna drabinka ≤ 32).
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
