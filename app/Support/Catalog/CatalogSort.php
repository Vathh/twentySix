<?php

namespace App\Support\Catalog;

use Illuminate\Database\Eloquent\Builder;

/** Sortowanie list katalogu: data wydarzenia, aktywność albo liczba powiązanych użytkowników. */
final class CatalogSort
{
    public const DATE = 'date';

    public const ACTIVITY = 'activity';

    public const MEMBERS = 'members';

    public static function from(mixed $raw): string
    {
        return match ($raw) {
            self::MEMBERS => self::MEMBERS,
            self::DATE => self::DATE,
            default => self::ACTIVITY,
        };
    }

    /** Domyślnie data wydarzenia (najnowsze pierwsze). */
    public static function fromTournament(mixed $raw): string
    {
        return match ($raw) {
            self::MEMBERS => self::MEMBERS,
            self::ACTIVITY => self::ACTIVITY,
            default => self::DATE,
        };
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

        if ($sort === self::DATE) {
            $query->orderByRaw('COALESCE(date, ?) DESC', ['1970-01-01'])
                ->orderByDesc('id');

            return true;
        }

        return false;
    }
}
