<?php

namespace App\Support\Catalog;

use Illuminate\Database\Eloquent\Builder;

/** Sortowanie list katalogu: ostatnia aktywność albo liczba powiązanych użytkowników. */
final class CatalogSort
{
    public const ACTIVITY = 'activity';

    public const MEMBERS = 'members';

    public static function from(mixed $raw): string
    {
        return $raw === self::MEMBERS ? self::MEMBERS : self::ACTIVITY;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function apply(Builder $query, ?string $sort, string $membersColumn): bool
    {
        if ($sort === self::MEMBERS) {
            $query->orderByDesc($membersColumn)
                ->orderByDesc('updated_at')
                ->orderByDesc('id');

            return true;
        }

        if ($sort === self::ACTIVITY) {
            $query->orderByDesc('updated_at')
                ->orderByDesc('id');

            return true;
        }

        return false;
    }
}
