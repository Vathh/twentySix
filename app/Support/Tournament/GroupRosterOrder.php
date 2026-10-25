<?php

namespace App\Support\Tournament;

/**
 * Stała kolejność zawodników w tabeli grupy: alfabetycznie, niezależnie od miejsca.
 */
final class GroupRosterOrder
{
    /**
     * @template T
     *
     * @param  iterable<T>  $rows
     * @param  callable(T): ?string  $name
     * @return list<T>
     */
    public static function byName(iterable $rows, callable $name): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = $row;
        }

        usort($items, function ($left, $right) use ($name) {
            return strnatcasecmp(trim((string) $name($left)), trim((string) $name($right)));
        });

        return $items;
    }
}
