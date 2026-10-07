<?php

namespace App\Support\Catalog;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Faza sezonu organizacji wyliczona z dat, nie z kolumny statusu.
 *
 * Zakończony: data końca jest przed dziś.
 * Zaplanowany: data startu jest po dziś i sezon nie jest zakończony.
 * W trakcie: jest choć jedna data, a sezon nie jest ani zakończony, ani zaplanowany.
 * Dziś liczy się jako dzień trwania. Sezon bez dat nie wpada do żadnej fazy.
 */
final class SeasonCatalogStatus
{
    public const PLANNED = 'planned';

    public const LIVE = 'live';

    public const FINISHED = 'finished';

    public static function from(mixed $raw): ?string
    {
        return in_array($raw, [self::PLANNED, self::LIVE, self::FINISHED], true) ? $raw : null;
    }

    public static function phase(?Carbon $start, ?Carbon $end, ?Carbon $today = null): ?string
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $startDay = $start?->copy()->startOfDay();
        $endDay = $end?->copy()->startOfDay();

        if ($endDay !== null && $endDay->lt($today)) {
            return self::FINISHED;
        }

        if ($startDay !== null && $startDay->gt($today)) {
            return self::PLANNED;
        }

        if ($startDay !== null || $endDay !== null) {
            return self::LIVE;
        }

        return null;
    }

    public static function label(?string $phase): ?string
    {
        return match ($phase) {
            self::PLANNED => 'Zaplanowany',
            self::LIVE => 'W trakcie',
            self::FINISHED => 'Zakończony',
            default => null,
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function apply(Builder $query, ?string $filter): void
    {
        $today = Carbon::today()->toDateString();

        if ($filter === self::FINISHED) {
            $query->whereDate('seasons.end_date', '<', $today);

            return;
        }

        if ($filter === self::PLANNED) {
            $query->where(function (Builder $open) use ($today) {
                $open->whereNull('seasons.end_date')
                    ->orWhereDate('seasons.end_date', '>=', $today);
            })->whereDate('seasons.start_date', '>', $today);

            return;
        }

        if ($filter === self::LIVE) {
            $query->where(function (Builder $open) use ($today) {
                $open->whereNull('seasons.end_date')
                    ->orWhereDate('seasons.end_date', '>=', $today);
            })->where(function (Builder $started) use ($today) {
                $started->whereNull('seasons.start_date')
                    ->orWhereDate('seasons.start_date', '<=', $today);
            })->where(function (Builder $dated) {
                $dated->whereNotNull('seasons.start_date')
                    ->orWhereNotNull('seasons.end_date');
            });
        }
    }
}
