<?php

namespace App\Support\Catalog;

use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Builder;

/** Filtr listy turniejów: zaplanowane, w trakcie (grupy lub playoff), zakończone. */
final class TournamentCatalogStatus
{
    public const PLANNED = 'planned';

    public const LIVE = 'live';

    public const FINISHED = 'finished';

    public static function from(mixed $raw): ?string
    {
        return in_array($raw, [self::PLANNED, self::LIVE, self::FINISHED], true) ? $raw : null;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function apply(Builder $query, ?string $filter): void
    {
        $statuses = match ($filter) {
            self::PLANNED => [TournamentStatus::CREATED->value],
            self::LIVE => [TournamentStatus::GROUP->value, TournamentStatus::PLAYOFF->value],
            self::FINISHED => [TournamentStatus::FINISHED->value],
            default => [],
        };

        if ($statuses === []) {
            return;
        }

        $query->whereIn('tournaments.status', $statuses);
    }
}
